<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrix;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerProcessAdapter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ConstraintPlanner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\DependencyStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\InstallStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\PreflightStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RealStepTest extends TestCase
{
    private string $workingDirectory;

    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir().'/laravel-upgrade-'.bin2hex(random_bytes(8));
        mkdir($this->workingDirectory, 0777, true);
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"php":"^8.2","laravel/framework":"^10.0"}}');
    }

    protected function tearDown(): void
    {
        foreach (['composer.json', 'composer.lock'] as $file) {
            $path = $this->workingDirectory.'/'.$file;

            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->workingDirectory)) {
            rmdir($this->workingDirectory);
        }
    }

    public function test_preflight_rejects_an_insufficient_php_version_without_running_commands(): void
    {
        $runner = new FakeProcessRunner;
        $result = $this->preflight($runner, phpVersionId: 80100)->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame(2, $result->exitCode);
        self::assertSame('php', $result->data['check']);
        self::assertSame([], $runner->requests);
    }

    public function test_preflight_reports_composer_validation_failure(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult(['composer', '--version'], 0, 'Composer version 2.7.0'),
            new ProcessResult(['composer', 'validate'], 1, '', 'Invalid composer.json'),
        ]);
        $result = $this->preflight($runner)->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame(2, $result->exitCode);
        self::assertSame('composer-json', $result->data['check']);
        $details = $result->data['details'] ?? null;
        self::assertIsArray($details);
        $output = $details['output'] ?? null;
        self::assertIsString($output);
        self::assertStringContainsString('Invalid composer.json', $output);
    }

    public function test_preflight_checks_sqlite_only_when_configured_and_rejects_old_versions(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult(['composer', '--version'], 0, 'Composer version 2.7.0'),
            new ProcessResult(['composer', 'validate'], 0, 'valid'),
        ]);
        $context = $this->context(['sqliteConfigured' => true, 'sqliteVersion' => '3.25.0']);
        $result = $this->preflight($runner, curlVersion: '7.34.0')->execute($context);

        self::assertTrue($result->isFailed());
        self::assertSame('sqlite', $result->data['check']);

        $runner = new FakeProcessRunner([
            new ProcessResult(['composer', '--version'], 0, 'Composer version 2.7.0'),
            new ProcessResult(['composer', 'validate'], 0, 'valid'),
        ]);
        $result = $this->preflight($runner, curlVersion: '7.34.0')->execute($this->context());

        self::assertTrue($result->isSuccessful(), $result->message.' '.json_encode($result->data));
    }

    public function test_preflight_rejects_old_curl_for_laravel_11(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult(['composer', '--version'], 0, 'Composer version 2.7.0'),
            new ProcessResult(['composer', 'validate'], 0, 'valid'),
        ]);
        $result = $this->preflight($runner, curlVersion: '7.33.0')->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame('curl', $result->data['check']);
    }

    public function test_preflight_accepts_a_clean_git_tree_when_git_safety_is_enabled(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult([], 0, 'Composer version 2.7.0'),
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, ''),
        ]);
        $result = $this->preflight($runner)->execute($this->context(['git' => true]));

        self::assertTrue($result->isSuccessful());
        self::assertSame('status', $runner->requests[2]->arguments[1]);
        self::assertSame('--porcelain', $runner->requests[2]->arguments[2]);
    }

    public function test_preflight_rejects_a_dirty_git_tree_when_git_safety_is_enabled(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult([], 0, 'Composer version 2.7.0'),
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, ' M app.php'),
        ]);
        $result = $this->preflight($runner)->execute($this->context(['git' => true]));

        self::assertTrue($result->isFailed());
        self::assertSame('git', $result->data['check']);
    }

    public function test_preflight_allows_a_dirty_git_tree_when_allow_dirty_is_enabled(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult([], 0, 'Composer version 2.7.0'),
            new ProcessResult([], 0, 'valid'),
        ]);
        $result = $this->preflight($runner)->execute($this->context(['git' => true, 'allowDirty' => true]));

        self::assertTrue($result->isSuccessful());
        self::assertCount(2, $runner->requests);
    }

    public function test_plan_dependency_step_does_not_modify_composer_json(): void
    {
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"php":"^8.2","laravel/framework":"^10.0","doctrine/dbal":"^3.6"}}');
        $before = file_get_contents($this->workingDirectory.'/composer.json');
        $runner = new FakeProcessRunner([new ProcessResult([], 0, 'previewed')]);
        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message.' '.json_encode($result->data));
        self::assertSame($before, file_get_contents($this->workingDirectory.'/composer.json'));
        self::assertCount(1, $runner->requests);
        self::assertSame(
            ['require', 'laravel/framework:^11.0.0', '--dry-run', '--with-all-dependencies', '--no-interaction'],
            array_slice($runner->requests[0]->arguments, -5),
        );
        self::assertNotContains('doctrine/dbal:^3.6', $runner->requests[0]->arguments);
        self::assertNotEmpty($result->data['decisions']);
        $notSolverPreviewed = $result->data['notSolverPreviewed'] ?? null;
        self::assertIsArray($notSolverPreviewed);
        self::assertSame(['doctrine/dbal'], $notSolverPreviewed['removals'] ?? null);
    }

    public function test_plan_dependency_step_uses_a_separate_dev_preview(): void
    {
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"},"require-dev":{"phpunit/phpunit":"^9.0"}}');
        $runner = new FakeProcessRunner([
            new ProcessResult([], 0, 'require preview'),
            new ProcessResult([], 0, 'dev preview'),
        ]);
        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertCount(2, $runner->requests);
        self::assertSame('require', $runner->requests[0]->arguments[1]);
        self::assertSame('require', $runner->requests[1]->arguments[1]);
        self::assertContains('phpunit/phpunit:^10.1.0', $runner->requests[1]->arguments);
        self::assertContains('--dev', $runner->requests[1]->arguments);
        self::assertSame(file_get_contents($this->workingDirectory.'/composer.json'), '{"require":{"laravel/framework":"^10.0"},"require-dev":{"phpunit/phpunit":"^9.0"}}');
    }

    public function test_apply_dependency_step_runs_no_update_commands_then_validation_and_solver(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult([], 0, 'required'),
            new ProcessResult([], 0, 'valid'),
            new ProcessResult([], 0, 'solvable'),
        ]);
        $result = $this->dependencyStep($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful(), $result->message.' '.json_encode($result->data));
        self::assertCount(3, $runner->requests);
        self::assertSame('require', $runner->requests[0]->arguments[1]);
        self::assertSame('validate', $runner->requests[1]->arguments[1]);
        self::assertSame('update', $runner->requests[2]->arguments[1]);
        self::assertContains('--dry-run', $runner->requests[2]->arguments);
    }

    public function test_install_is_skipped_in_plan_mode_and_when_disabled(): void
    {
        $runner = new FakeProcessRunner;
        $step = new InstallStep(new ComposerProcessAdapter($runner));

        self::assertTrue($step->execute($this->context(planMode: true))->isSkipped());
        self::assertTrue($step->execute($this->context(['noInstall' => true]))->isSkipped());
        self::assertSame([], $runner->requests);
    }

    public function test_install_runs_update_then_dump_autoload_and_reports_failure(): void
    {
        $runner = new FakeProcessRunner([
            new ProcessResult([], 0, 'updated'),
            new ProcessResult([], 0, 'autoloaded'),
        ]);
        $step = new InstallStep(new ComposerProcessAdapter($runner));
        $result = $step->execute($this->context());

        self::assertTrue($result->isSuccessful());
        self::assertCount(2, $runner->requests);
        self::assertSame('update', $runner->requests[0]->arguments[1]);
        self::assertSame('dump-autoload', $runner->requests[1]->arguments[1]);

        $runner = new FakeProcessRunner([new ProcessResult([], 7, '', 'solver failed')]);
        $result = (new InstallStep(new ComposerProcessAdapter($runner)))->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame(3, $result->exitCode);
        $processes = $result->data['processes'] ?? null;
        self::assertIsArray($processes);
        $process = $processes[0] ?? null;
        self::assertIsArray($process);
        $output = $process['output'] ?? null;
        self::assertIsString($output);
        self::assertStringContainsString('solver failed', $output);
    }

    public function test_binary_resolver_uses_php_and_explicit_composer_binary(): void
    {
        $resolver = new BinaryResolver;

        self::assertSame(PHP_BINARY, $resolver->phpBinary());
        self::assertSame('/custom/composer', $resolver->composerBinary('/custom/composer'));
    }

    public function test_combined_process_output_has_a_separator_only_when_needed(): void
    {
        self::assertSame("stdout\nstderr", (new ProcessResult([], 1, 'stdout', 'stderr'))->combinedOutput());
        self::assertSame("stdout\nstderr", (new ProcessResult([], 1, "stdout\n", 'stderr'))->combinedOutput());
        self::assertSame('stdout', (new ProcessResult([], 0, 'stdout'))->combinedOutput());
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function context(array $options = [], bool $planMode = false): UpgradeContext
    {
        return new UpgradeContext(
            $this->workingDirectory,
            new UpgradePlan(10, 11, $planMode),
            'test-run',
            $options,
        );
    }

    private function preflight(
        FakeProcessRunner $runner,
        ?int $phpVersionId = null,
        ?string $curlVersion = '7.80.0',
    ): PreflightStep {
        return new PreflightStep(
            $runner,
            dirname(__DIR__, 3).'/resources/compat/php.json',
            phpVersionId: $phpVersionId,
            curlVersion: $curlVersion,
            loadedExtensions: array_fill_keys(
                ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml'],
                true,
            ),
        );
    }

    private function dependencyStep(FakeProcessRunner $runner): DependencyStep
    {
        return new DependencyStep(
            new ConstraintPlanner(
                new CompatibilityMatrix(dirname(__DIR__, 3).'/resources/compat/packages.json'),
                dirname(__DIR__, 3).'/resources/compat/removals.json',
            ),
            new ComposerProcessAdapter($runner),
        );
    }
}

/** @internal */
final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    /** @var list<ProcessResult> */
    private array $results;

    /**
     * @param  list<ProcessResult>  $results
     */
    public function __construct(array $results = [])
    {
        $this->results = $results;
    }

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;

        if ($this->results === []) {
            throw new RuntimeException('Unexpected process: '.$request->executable());
        }

        $result = array_shift($this->results);

        if (! $result instanceof ProcessResult) {
            throw new RuntimeException('Fake process result queue was corrupted.');
        }

        return new ProcessResult($request->arguments, $result->exitCode, $result->output, $result->errorOutput);
    }
}
