<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\ProjectAdvisor;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\AdvisoryStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdvisoryStepTest extends TestCase
{
    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir().'/laravel-upgrade-advisory-'.bin2hex(random_bytes(8));
        mkdir($this->projectDirectory.'/app', 0777, true);
        mkdir($this->projectDirectory.'/config', 0777, true);
        mkdir($this->projectDirectory.'/vendor/bin', 0777, true);
        file_put_contents($this->projectDirectory.'/app/Example.php', "<?php\nreturn true;\n");
        file_put_contents($this->projectDirectory.'/vendor/bin/phpstan', 'fake phpstan');
        file_put_contents($this->projectDirectory.'/composer.json', '{"require":{"laravel/framework":"^10.0"}}');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDirectory);
    }

    public function test_phpstan_exit_one_with_valid_findings_is_a_successful_advisory_pass(): void
    {
        $runner = new AdvisoryFakeProcessRunner([
            new ProcessResult([], 1, $this->phpstanJson([
                'message' => 'Review this advisory.',
                'line' => 2,
                'identifier' => 'laravelUpgrade.example',
                'tip' => 'Check the upgrade guide.',
                'metadata' => [
                    'severity' => 'high',
                    'confidence' => 'low',
                    'guideUrl' => 'https://example.test/upgrade',
                ],
            ])),
        ]);

        $result = $this->step($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame(2, $result->findingsCount);
        $process = $result->data['process'] ?? null;
        self::assertIsArray($process);
        self::assertSame(1, $process['exitCode'] ?? null);
        $findings = $result->data['findings'] ?? null;
        self::assertIsArray($findings);
        self::assertSame(
            ['laravelUpgrade.example', 'laravelUpgrade.laravelInstaller'],
            array_column($findings, 'ruleId'),
        );
        $finding = $findings[0] ?? null;
        self::assertIsArray($finding);
        self::assertSame('laravelUpgrade.example', $finding['ruleId'] ?? null);
        self::assertSame('high', $finding['severity'] ?? null);
        self::assertSame('Check the upgrade guide.', $finding['action'] ?? null);
        self::assertSame('low', $finding['confidence'] ?? null);
        self::assertSame('https://example.test/upgrade', $finding['guideUrl'] ?? null);
    }

    public function test_invalid_json_and_phpstan_internal_errors_fail_the_step(): void
    {
        $invalid = $this->step(new AdvisoryFakeProcessRunner([
            new ProcessResult([], 1, 'not json', 'parser output'),
        ]))->execute($this->context(planMode: true));

        self::assertTrue($invalid->isFailed());
        self::assertSame('phpstan-json', $invalid->data['check'] ?? null);

        $internal = $this->step(new AdvisoryFakeProcessRunner([
            new ProcessResult([], 1, $this->phpstanJson([], ['PHPStan crashed'])),
        ]))->execute($this->context(planMode: true));

        self::assertTrue($internal->isFailed());
        self::assertSame('phpstan-internal-error', $internal->data['check'] ?? null);
    }

    public function test_plan_is_byte_neutral_and_never_annotates(): void
    {
        $before = file_get_contents($this->projectDirectory.'/app/Example.php');
        $runner = new AdvisoryFakeProcessRunner([
            new ProcessResult([], 0, $this->phpstanJson([
                'message' => 'Plan-only advisory.',
                'line' => 2,
                'identifier' => 'laravelUpgrade.plan',
            ])),
        ]);

        $result = $this->step($runner)->execute($this->context(['annotate' => true], planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertSame($before, file_get_contents($this->projectDirectory.'/app/Example.php'));
        self::assertStringNotContainsString('TODO(laravel-upgrade:', (string) file_get_contents($this->projectDirectory.'/app/Example.php'));
        self::assertDirectoryDoesNotExist($this->projectDirectory.'/.laravel-upgrade');
        self::assertStringContainsString(sys_get_temp_dir(), $runner->requests[0]->arguments[3]);
        $annotation = $result->data['annotation'] ?? null;
        self::assertIsArray($annotation);
        self::assertSame('skipped', $annotation['status'] ?? null);
    }

    public function test_apply_writes_config_and_findings_and_can_annotate(): void
    {
        file_put_contents($this->projectDirectory.'/config/database.php', "<?php\nreturn ['connections' => ['sqlite' => ['driver' => 'sqlite']]];\n");
        file_put_contents($this->projectDirectory.'/config/queue.php', "<?php\nreturn ['default' => env('QUEUE_CONNECTION', 'database')];\n");
        file_put_contents($this->projectDirectory.'/config/session.php', "<?php\nreturn ['serialization' => 'php'];\n");
        file_put_contents($this->projectDirectory.'/.env', "DB_CONNECTION=sqlite\nQUEUE_CONNECTION=sync\n");
        file_put_contents($this->projectDirectory.'/phpstan.neon', "parameters:\n    level: 0\n");
        mkdir($this->projectDirectory.'/vendor/larastan/larastan', 0777, true);
        file_put_contents($this->projectDirectory.'/vendor/larastan/larastan/extension.neon', "parameters:\n    level: 0\n");

        $runner = new AdvisoryFakeProcessRunner([
            new ProcessResult([], 0, $this->phpstanJson([
                'message' => 'Annotate this advisory.',
                'line' => 2,
                'identifier' => 'laravelUpgrade.annotate',
            ])),
        ]);
        $result = $this->step($runner)->execute($this->context(['annotate' => true]));

        self::assertTrue($result->isSuccessful(), $result->message);
        $configPath = $this->projectDirectory.'/.laravel-upgrade/phpstan-11.neon';
        $findingsPath = $this->projectDirectory.'/.laravel-upgrade/findings.jsonl';
        self::assertFileExists($configPath);
        self::assertFileExists($findingsPath);
        $config = (string) file_get_contents($configPath);
        self::assertStringContainsString('resources/phpstan/upgrade-11.neon', str_replace('\\', '/', $config));
        self::assertStringNotContainsString($this->projectDirectory.'/phpstan.neon', str_replace('\\', '/', $config));
        self::assertStringContainsString('larastan/larastan/extension.neon', str_replace('\\', '/', $config));
        self::assertStringContainsString('databaseDrivers:', $config);
        self::assertStringContainsString("- 'sqlite'", $config);
        self::assertStringContainsString("queueDefault: 'sync'", $config);
        self::assertStringContainsString("sessionSerialization: 'php'", $config);
        self::assertStringContainsString('TODO(laravel-upgrade:laravelUpgrade.annotate): Annotate this advisory.', (string) file_get_contents($this->projectDirectory.'/app/Example.php'));
        self::assertGreaterThanOrEqual(2, count(file($findingsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []));
    }

    public function test_advisor_findings_are_merged_without_duplicate_locations(): void
    {
        mkdir($this->projectDirectory.'/resources/views/vendor/widgets', 0777, true);
        $message = [
            'message' => 'Published vendor views found under "resources/views/vendor/widgets".',
            'line' => 0,
            'identifier' => 'laravelUpgrade.publishedVendorViews',
        ];
        $runner = new AdvisoryFakeProcessRunner([
            new ProcessResult([], 0, $this->phpstanJson($message, [], 'resources/views/vendor/widgets')),
        ]);

        $result = $this->step($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertSame(2, $result->findingsCount);
    }

    public function test_apply_advisories_preserve_prior_findings_and_are_idempotent(): void
    {
        $runner = new AdvisoryFakeProcessRunner([
            new ProcessResult([], 0, $this->phpstanJson([
                'message' => 'First advisory.',
                'line' => 2,
                'identifier' => 'laravelUpgrade.first',
            ])),
            new ProcessResult([], 0, $this->phpstanJson([
                'message' => 'Second advisory.',
                'line' => 2,
                'identifier' => 'laravelUpgrade.second',
            ])),
            new ProcessResult([], 0, $this->phpstanJson([
                'message' => 'First advisory.',
                'line' => 2,
                'identifier' => 'laravelUpgrade.first',
            ])),
        ]);
        $step = $this->step($runner);

        $first = $step->execute($this->context());
        $second = $step->execute($this->context());
        $third = $step->execute($this->context());

        self::assertTrue($first->isSuccessful(), $first->message);
        self::assertTrue($second->isSuccessful(), $second->message);
        self::assertTrue($third->isSuccessful(), $third->message);
        self::assertSame(2, $second->findingsCount);
        self::assertSame(2, $third->findingsCount);

        $lines = file($this->projectDirectory.'/.laravel-upgrade/findings.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(3, $lines);
        $findings = array_map(
            static function (string $line): array {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($decoded)) {
                    throw new \UnexpectedValueException('Expected a finding object.');
                }

                return $decoded;
            },
            $lines,
        );
        self::assertSame(
            ['laravelUpgrade.first', 'laravelUpgrade.laravelInstaller', 'laravelUpgrade.second'],
            array_column($findings, 'ruleId'),
        );
        self::assertSame(['f-0001', 'f-0002', 'f-0003'], array_column($findings, 'id'));
    }

    public function test_binary_and_analysis_paths_cannot_escape_the_project(): void
    {
        $binary = $this->step(new AdvisoryFakeProcessRunner)->execute($this->context([
            'phpstanBinary' => '../vendor/bin/phpstan',
        ], planMode: true));
        $outside = $this->step(new AdvisoryFakeProcessRunner)->execute($this->context([
            'phpstanBinary' => '/bin/sh',
        ], planMode: true));
        $paths = $this->step(new AdvisoryFakeProcessRunner)->execute($this->context([
            'phpstanPaths' => ['app/../config'],
        ], planMode: true));

        self::assertTrue($binary->isFailed());
        self::assertSame('phpstan-binary', $binary->data['check'] ?? null);
        self::assertTrue($outside->isFailed());
        self::assertSame('phpstan-binary', $outside->data['check'] ?? null);
        self::assertTrue($paths->isFailed());
        self::assertSame('phpstan-paths', $paths->data['check'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function context(array $options = [], bool $planMode = false): UpgradeContext
    {
        return new UpgradeContext(
            $this->projectDirectory,
            new UpgradePlan(10, 11, $planMode),
            'advisory-test',
            $options,
        );
    }

    private function step(AdvisoryFakeProcessRunner $runner): AdvisoryStep
    {
        return new AdvisoryStep(
            $runner,
            projectAdvisorFactory: static fn (string $configDirectory, int $targetMajor): ProjectAdvisor => new ProjectAdvisor(
                $configDirectory,
                $targetMajor,
                static fn (): string => '2.0.0',
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  list<string>  $errors
     */
    private function phpstanJson(array $message = [], array $errors = [], ?string $file = null): string
    {
        $file ??= $this->projectDirectory.'/app/Example.php';
        $messages = $message === [] ? [] : [$message];

        return json_encode([
            'totals' => ['errors' => count($messages), 'file_errors' => count($messages)],
            'files' => [
                $file => [
                    'errors' => count($messages),
                    'messages' => $messages,
                ],
            ],
            'errors' => $errors,
        ], JSON_THROW_ON_ERROR);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}

/** @internal */
final class AdvisoryFakeProcessRunner implements ProcessRunner
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
