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
        foreach (['composer.json', 'composer.lock', 'auth.json', 'composer.phar'] as $file) {
            $path = $this->workingDirectory.'/'.$file;

            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (['packages', 'tools'] as $directory) {
            $this->removeDirectory($this->workingDirectory.'/'.$directory);
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
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));
        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message.' '.json_encode($result->data));
        self::assertSame($before, file_get_contents($this->workingDirectory.'/composer.json'));
        self::assertCount(1, $runner->requests);
        self::assertSame(
            ['update', '--dry-run', '--with-all-dependencies', '--no-interaction'],
            array_slice($runner->requests[0]->arguments, -4),
        );
        self::assertNotSame($this->workingDirectory, $runner->workingDirectory);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
        self::assertNotContains('doctrine/dbal:^3.6', $runner->requests[0]->arguments);
        self::assertNotEmpty($result->data['decisions']);
        $solverPreview = $result->data['solverPreview'] ?? null;
        self::assertIsArray($solverPreview);
        self::assertTrue($solverPreview['combined'] ?? false);
        self::assertSame(['doctrine/dbal'], $solverPreview['removals'] ?? null);
    }

    public function test_plan_dependency_step_uses_one_combined_preview_for_both_sections(): void
    {
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"},"require-dev":{"phpunit/phpunit":"^9.0"}}');
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'combined preview'));
        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertCount(1, $runner->requests);
        self::assertSame('update', $runner->requests[0]->arguments[1]);
        $require = $runner->manifest['require'] ?? null;
        $requireDev = $runner->manifest['require-dev'] ?? null;
        self::assertIsArray($require);
        self::assertIsArray($requireDev);
        self::assertSame('^11.0.0', $require['laravel/framework'] ?? null);
        self::assertSame('^10.1.0', $requireDev['phpunit/phpunit'] ?? null);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
        self::assertSame(file_get_contents($this->workingDirectory.'/composer.json'), '{"require":{"laravel/framework":"^10.0"},"require-dev":{"phpunit/phpunit":"^9.0"}}');
    }

    public function test_plan_preview_coordinated_production_and_dev_bumps_are_checksum_neutral(): void
    {
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"php":"^8.1","laravel/framework":"^10.0"},"require-dev":{"nunomaduro/collision":"^7.0"}}');
        file_put_contents($this->workingDirectory.'/composer.lock', '{"packages":[],"packages-dev":[]}');
        $before = $this->treeChecksums();
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'combined preview'));

        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame($before, $this->treeChecksums());
        $require = $runner->manifest['require'] ?? null;
        $requireDev = $runner->manifest['require-dev'] ?? null;
        self::assertIsArray($require);
        self::assertIsArray($requireDev);
        self::assertSame('^8.2.0', $require['php'] ?? null);
        self::assertSame('^11.0.0', $require['laravel/framework'] ?? null);
        self::assertSame('^8.1.0', $requireDev['nunomaduro/collision'] ?? null);
        self::assertTrue($runner->lockExisted);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_cleans_temporary_workspace_after_solver_failure(): void
    {
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"},"require-dev":{"nunomaduro/collision":"^7.0"}}');
        $before = $this->treeChecksums();
        $runner = new InspectingPreviewRunner(new ProcessResult([], 3, '', 'solver failed'));

        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isFailed());
        self::assertSame(3, $result->exitCode);
        self::assertSame($before, $this->treeChecksums());
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_preserves_json_shapes_lock_auth_and_isolated_environment(): void
    {
        $manifest = <<<'JSON'
{
    "require": {
        "laravel/framework": "^10.0"
    },
    "require-dev": {},
    "config": {
        "allow-plugins": {}
    }
}
JSON;
        $lock = "{\n    \"packages\": [],\n    \"packages-dev\": []\n}\n";
        $auth = "{\n    \"http-basic\": {\n        \"repo.example\": {\n            \"username\": \"preview-user\",\n            \"password\": \"preview-secret\"\n        }\n    }\n}\n";
        file_put_contents($this->workingDirectory.'/composer.json', $manifest);
        file_put_contents($this->workingDirectory.'/composer.lock', $lock);
        file_put_contents($this->workingDirectory.'/auth.json', $auth);
        $before = $this->treeChecksums();
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));
        $oldComposer = getenv('COMPOSER');
        $oldComposerAuth = getenv('COMPOSER_AUTH');
        $oldComposerHome = getenv('COMPOSER_HOME');
        putenv('COMPOSER='.$this->workingDirectory.'/composer.json');
        putenv('COMPOSER_AUTH={"token":"sentinel-secret"}');
        putenv('COMPOSER_HOME=/private/sentinel-composer-home');
        $authDuringPreview = false;
        $homeDuringPreview = false;

        try {
            $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));
            $authDuringPreview = getenv('COMPOSER_AUTH');
            $homeDuringPreview = getenv('COMPOSER_HOME');
        } finally {
            $this->restoreEnvironmentVariable('COMPOSER', $oldComposer);
            $this->restoreEnvironmentVariable('COMPOSER_AUTH', $oldComposerAuth);
            $this->restoreEnvironmentVariable('COMPOSER_HOME', $oldComposerHome);
        }

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame($before, $this->treeChecksums());
        self::assertStringContainsString('"require-dev": {}', $runner->manifestJson);
        self::assertStringContainsString('"allow-plugins": {}', $runner->manifestJson);
        self::assertSame($lock, $runner->lockContents);
        self::assertSame($auth, $runner->authContents);
        self::assertSame(0600, $runner->authMode);
        self::assertSame($runner->workingDirectory.'/composer.json', $runner->environment['COMPOSER'] ?? null);
        self::assertArrayNotHasKey('COMPOSER_AUTH', $runner->environment);
        self::assertArrayNotHasKey('COMPOSER_HOME', $runner->environment);
        self::assertSame($runner->workingDirectory.'/cache', $runner->environment['COMPOSER_CACHE_DIR'] ?? null);
        self::assertArrayNotHasKey('COMPOSER_CONFIG_DIR', $runner->environment);
        self::assertSame('{"token":"sentinel-secret"}', $authDuringPreview);
        self::assertSame('/private/sentinel-composer-home', $homeDuringPreview);
        self::assertSame(0700, $runner->workingDirectoryMode);
        self::assertStringNotContainsString('preview-secret', $result->message);
        self::assertStringNotContainsString('sentinel-secret', json_encode($result->data, JSON_THROW_ON_ERROR));
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_canonicalizes_relative_path_repositories_without_changing_options(): void
    {
        $packageDirectory = $this->workingDirectory.'/packages/shared';
        mkdir($packageDirectory, 0777, true);
        file_put_contents($packageDirectory.'/composer.json', '{"name":"acme/shared","version":"1.0.0"}');
        file_put_contents($this->workingDirectory.'/composer.json', <<<'JSON'
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/shared",
            "options": {
                "symlink": false,
                "versions": {
                    "acme/shared": "1.0.0"
                }
            }
        }
    ],
    "require": {
        "laravel/framework": "^10.0"
    }
}
JSON);
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));

        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        $repositories = $runner->manifest['repositories'] ?? null;
        self::assertIsArray($repositories);
        $repository = $repositories[0] ?? null;
        self::assertIsArray($repository);
        self::assertSame(realpath($packageDirectory), $repository['url'] ?? null);
        self::assertSame([
            'symlink' => false,
            'versions' => ['acme/shared' => '1.0.0'],
        ], $repository['options'] ?? null);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_canonicalizes_relative_path_repositories_in_an_object_map(): void
    {
        $packageDirectory = $this->workingDirectory.'/packages/shared';
        mkdir($packageDirectory, 0777, true);
        file_put_contents($packageDirectory.'/composer.json', '{"name":"acme/shared","version":"1.0.0"}');
        file_put_contents($this->workingDirectory.'/composer.json', <<<'JSON'
{
    "repositories": {
        "local": {
            "type": "path",
            "url": "packages/shared",
            "options": {
                "symlink": false
            }
        }
    },
    "require": {
        "laravel/framework": "^10.0"
    }
}
JSON);
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));

        $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        $repositories = $runner->manifest['repositories'] ?? null;
        self::assertIsArray($repositories);
        $repository = $repositories['local'] ?? null;
        self::assertIsArray($repository);
        self::assertSame(realpath($packageDirectory), $repository['url'] ?? null);
        self::assertSame(['symlink' => false], $repository['options'] ?? null);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_resolves_a_relative_explicit_composer_binary_against_the_project(): void
    {
        mkdir($this->workingDirectory.'/tools', 0777, true);
        file_put_contents($this->workingDirectory.'/tools/composer', '#!/bin/sh\n');
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"}}');
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));

        $result = $this->dependencyStep($runner)->execute($this->context([
            'composerBinary' => 'tools/composer',
        ], planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame(realpath($this->workingDirectory.'/tools/composer'), $runner->requests[0]->arguments[0]);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_resolves_a_relative_composer_binary_from_environment(): void
    {
        mkdir($this->workingDirectory.'/tools', 0777, true);
        file_put_contents($this->workingDirectory.'/tools/composer', '#!/bin/sh\n');
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"}}');
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));
        $oldComposerBinary = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY=tools/composer');

        try {
            $result = $this->dependencyStep($runner)->execute($this->context(planMode: true));
        } finally {
            $this->restoreEnvironmentVariable('COMPOSER_BINARY', $oldComposerBinary);
        }

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame(realpath($this->workingDirectory.'/tools/composer'), $runner->requests[0]->arguments[0]);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
    }

    public function test_plan_preview_resolves_a_bare_project_composer_file(): void
    {
        file_put_contents($this->workingDirectory.'/composer.phar', '#!/usr/bin/env php\n');
        file_put_contents($this->workingDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"}}');
        $runner = new InspectingPreviewRunner(new ProcessResult([], 0, 'previewed'));

        $result = $this->dependencyStep($runner)->execute($this->context([
            'composerBinary' => 'composer.phar',
        ], planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame(realpath($this->workingDirectory.'/composer.phar'), $runner->requests[0]->arguments[0]);
        self::assertDirectoryDoesNotExist($runner->workingDirectory);
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

    /** @return array<string, string> */
    private function treeChecksums(): array
    {
        $checksums = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workingDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $relative = str_replace($this->workingDirectory.'/', '', $fileInfo->getPathname());
            $checksums[$relative] = md5_file($fileInfo->getPathname()) ?: '';
        }

        ksort($checksums);

        return $checksums;
    }

    private function restoreEnvironmentVariable(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);

            return;
        }

        putenv($name.'='.$value);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
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

    private function dependencyStep(ProcessRunner $runner): DependencyStep
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

/** @internal */
final class InspectingPreviewRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    /** @var array<string, mixed> */
    public array $manifest = [];

    public string $manifestJson = '';

    public string $lockContents = '';

    public string $authContents = '';

    public int $authMode = 0;

    /** @var array<string, string> */
    public array $environment = [];

    public bool $lockExisted = false;

    public string $workingDirectory = '';

    public int $workingDirectoryMode = 0;

    public function __construct(private readonly ProcessResult $result) {}

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;
        $this->workingDirectory = $request->workingDirectory;
        $manifestJson = file_get_contents($request->workingDirectory.'/composer.json');

        if (! is_string($manifestJson)) {
            throw new RuntimeException('Preview manifest could not be read.');
        }

        $this->manifestJson = $manifestJson;
        $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($manifest)) {
            throw new RuntimeException('Preview manifest is not an object.');
        }

        $stringKeyManifest = [];

        foreach ($manifest as $key => $value) {
            if (is_string($key)) {
                $stringKeyManifest[$key] = $value;
            }
        }

        $this->manifest = $stringKeyManifest;
        $this->lockExisted = is_file($request->workingDirectory.'/composer.lock');
        $this->lockContents = $this->readOptionalFile($request->workingDirectory.'/composer.lock');
        $this->authContents = $this->readOptionalFile($request->workingDirectory.'/auth.json');
        $authPermissions = is_file($request->workingDirectory.'/auth.json')
            ? fileperms($request->workingDirectory.'/auth.json')
            : false;
        $this->authMode = is_int($authPermissions) ? $authPermissions & 0777 : 0;
        $this->environment = $request->environment ?? [];
        $permissions = fileperms($request->workingDirectory);
        $this->workingDirectoryMode = is_int($permissions) ? $permissions & 0777 : 0;

        return new ProcessResult($request->arguments, $this->result->exitCode, $this->result->output, $this->result->errorOutput);
    }

    private function readOptionalFile(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('Preview artifact could not be read.');
        }

        return $contents;
    }
}
