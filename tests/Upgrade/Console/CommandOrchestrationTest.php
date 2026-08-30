<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Application;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ContinueCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\PlanCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ToCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\ProjectVersionDetector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\SingleStepRuntimeInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeFactory;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\SupportPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandOrchestrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-upgrade-command-'.bin2hex(random_bytes(5));
        mkdir($this->directory.'/vendor/composer', 0777, true);
        file_put_contents($this->directory.'/composer.json', json_encode([
            'require' => ['laravel/framework' => '^10.0'],
        ], JSON_PRETTY_PRINT)."\n");
        file_put_contents($this->directory.'/vendor/composer/installed.json', json_encode([
            'packages' => [['name' => 'laravel/framework', 'version' => 'v10.48.0']],
        ]));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_to_uses_real_plan_and_writes_only_plan_json_in_plan_mode(): void
    {
        $before = $this->snapshotFiles();
        $runtime = new RecordingRuntime;
        $command = new CommandTester(new ToCommand($runtime));

        $exit = $command->execute([
            'target-major' => '13',
            '--plan' => true,
            '--working-dir' => $this->directory,
            '--skip-step' => ['skeleton,commit'],
            '--no-tests' => true,
            '--no-pint' => true,
            '--no-git' => true,
            '--no-interaction' => true,
        ]);

        self::assertSame(0, $exit);
        self::assertCount(1, $runtime->runs);
        self::assertSame([11, 12, 13], $runtime->runs[0]['plan']->transitions());
        self::assertSame(['skeleton', 'commit'], $runtime->runs[0]['plan']->skipSteps);
        self::assertTrue($runtime->runs[0]['plan']->isPlanMode());
        self::assertFileExists($this->directory.'/.laravel-upgrade/plan.json');
        self::assertFileDoesNotExist($this->directory.'/.laravel-upgrade/state.json');
        self::assertFileDoesNotExist($this->directory.'/.laravel-upgrade/findings.jsonl');
        $after = $this->snapshotFiles();
        $changed = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $path) {
            if (($before[$path] ?? null) !== ($after[$path] ?? null)) {
                $changed[] = $path;
            }
        }

        self::assertSame(['.laravel-upgrade/plan.json'], $changed);
    }

    public function test_plan_preserves_an_existing_apply_journal_and_only_writes_plan_json(): void
    {
        $store = new StateStore($this->directory);
        $store->start(new UpgradePlan(10, 12), 'apply-run');
        $before = $this->snapshotFiles();
        $runtime = new UpgradeRuntimeFactory(
            stepFactory: static function (
                ProcessRunner $processRunner,
                BinaryResolver $binaryResolver,
                GitCheckpointService $git,
            ): array {
                unset($processRunner, $binaryResolver, $git);
                $steps = [];

                foreach (UpgradePlan::canonicalStepNames() as $name) {
                    $steps[] = new PlanNoOpStep($name);
                }

                return $steps;
            },
        );

        $exit = (new CommandTester(new ToCommand($runtime)))->execute([
            'target-major' => '11',
            '--plan' => true,
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]);

        self::assertSame(0, $exit);
        $after = $this->snapshotFiles();
        $changed = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $path) {
            if (($before[$path] ?? null) !== ($after[$path] ?? null)) {
                $changed[] = $path;
            }
        }

        self::assertSame(['.laravel-upgrade/plan.json'], $changed);
        self::assertSame($before['.laravel-upgrade/state.json'] ?? null, $after['.laravel-upgrade/state.json'] ?? null);
    }

    public function test_current_target_is_a_no_op_without_runtime_or_artifacts(): void
    {
        file_put_contents($this->directory.'/vendor/composer/installed.json', json_encode([
            'packages' => [['name' => 'laravel/framework', 'version' => 'v11.0.0']],
        ]));
        $runtime = new RecordingRuntime;
        $command = new CommandTester(new ToCommand($runtime));

        self::assertSame(0, $command->execute([
            'target-major' => '11',
            '--plan' => true,
            '--working-dir' => $this->directory,
        ]));
        self::assertSame([], $runtime->runs);
        self::assertFileDoesNotExist($this->directory.'/.laravel-upgrade/plan.json');
    }

    public function test_version_detector_prefers_installed_then_lock_then_manifest_with_warning(): void
    {
        $detector = new ProjectVersionDetector;

        $installed = $detector->detect($this->directory);
        self::assertSame(10, $installed->major);
        self::assertSame('vendor/composer/installed.json', $installed->source);
        self::assertNull($installed->warning);

        unlink($this->directory.'/vendor/composer/installed.json');
        file_put_contents($this->directory.'/composer.lock', json_encode([
            'packages' => [['name' => 'laravel/framework', 'version' => 'v11.2.0']],
        ]));
        $locked = $detector->detect($this->directory);
        self::assertSame(11, $locked->major);
        self::assertSame('composer.lock', $locked->source);
        self::assertNotNull($locked->warning);

        unlink($this->directory.'/composer.lock');
        $manifest = $detector->detect($this->directory);
        self::assertSame(10, $manifest->major);
        self::assertSame('composer.json', $manifest->source);
        self::assertNotNull($manifest->warning);
    }

    public function test_failure_exit_code_is_returned_and_modern_structure_is_allowed_for_ten_to_eleven(): void
    {
        $runtime = new RecordingRuntime(UpgradeRunResult::failed(
            'code', '10->11', 4, [], [], 'code failed',
        ));
        $command = new CommandTester(new ToCommand($runtime));

        self::assertSame(4, $command->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]));
        self::assertSame(1, count($runtime->runs));

        $modern = new CommandTester(new ToCommand($runtime));
        self::assertSame(4, $modern->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
            '--structure' => 'modern',
        ]));
        self::assertSame(2, count($runtime->runs));
    }

    public function test_plan_command_preserves_common_options(): void
    {
        $runtime = new RecordingRuntime;
        $command = new CommandTester(new PlanCommand($runtime));

        self::assertSame(0, $command->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
            '--from-step' => 'code',
            '--composer' => 'tools/composer',
            '--no-tests' => true,
        ]));

        self::assertCount(1, $runtime->runs);
        self::assertSame('code', $runtime->runs[0]['plan']->fromStep);
        self::assertSame('tools/composer', $runtime->runs[0]['options']['composerBinary']);
        self::assertTrue($runtime->runs[0]['options']['noTests']);
    }

    public function test_to_command_passes_a_shifted_policy_into_the_constructed_plan(): void
    {
        file_put_contents($this->directory.'/composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ], JSON_PRETTY_PRINT)."\n");
        file_put_contents($this->directory.'/vendor/composer/installed.json', json_encode([
            'packages' => [['name' => 'laravel/framework', 'version' => 'v11.0.0']],
        ]));
        $policy = SupportPolicy::fromArray([
            '$schema' => SupportPolicy::SCHEMA,
            'schemaVersion' => 1,
            'maxPathCount' => 3,
            'paths' => [
                ['source' => 11, 'target' => 12],
                ['source' => 12, 'target' => 13],
                ['source' => 13, 'target' => 14],
            ],
            'sources' => [
                11 => ['phpMinimum' => '8.2.0', 'securityFixUntil' => '2026-03-12'],
                12 => ['phpMinimum' => '8.2.0', 'securityFixUntil' => '2027-02-24'],
                13 => ['phpMinimum' => '8.3.0', 'securityFixUntil' => '2028-02-24'],
            ],
        ]);
        $runtime = new RecordingRuntime;

        self::assertSame(0, (new CommandTester(new ToCommand(
            $runtime,
            new ProjectVersionDetector,
            supportPolicy: $policy,
        )))->execute([
            'target-major' => '14',
            '--plan' => true,
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]));

        self::assertCount(1, $runtime->runs);
        self::assertSame(14, $runtime->runs[0]['plan']->targetMajor);
        self::assertSame($policy, $runtime->runs[0]['plan']->supportPolicy());
    }

    public function test_application_passes_a_custom_policy_to_registered_commands(): void
    {
        file_put_contents($this->directory.'/composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ], JSON_PRETTY_PRINT)."\n");
        file_put_contents($this->directory.'/vendor/composer/installed.json', json_encode([
            'packages' => [['name' => 'laravel/framework', 'version' => 'v11.0.0']],
        ]));
        $policy = SupportPolicy::fromArray([
            '$schema' => SupportPolicy::SCHEMA,
            'schemaVersion' => 1,
            'maxPathCount' => 3,
            'paths' => [
                ['source' => 11, 'target' => 12],
                ['source' => 12, 'target' => 13],
                ['source' => 13, 'target' => 14],
            ],
            'sources' => [
                11 => ['phpMinimum' => '8.2.0', 'securityFixUntil' => '2026-03-12'],
                12 => ['phpMinimum' => '8.2.0', 'securityFixUntil' => '2027-02-24'],
                13 => ['phpMinimum' => '8.3.0', 'securityFixUntil' => '2028-02-24'],
            ],
        ]);
        $runtime = new RecordingRuntime;
        $application = new Application($runtime, new ApplicationRecordingSingleStepRuntime, $policy);

        self::assertSame(0, (new CommandTester($application->find('to')))->execute([
            'target-major' => '14',
            '--plan' => true,
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]));

        self::assertCount(1, $runtime->runs);
        self::assertSame($policy, $runtime->runs[0]['plan']->supportPolicy());
    }

    public function test_continue_reconstructs_target_and_persists_safe_override(): void
    {
        $store = new StateStore($this->directory);
        $store->start(new UpgradePlan(10, 11, false, 'code', 'commit'), 'run-id', [
            'noTests' => false,
            'fromStep' => 'code',
            'skipSteps' => ['commit'],
            'apiToken' => 'must-not-be-persisted',
        ]);
        $runtime = new RecordingRuntime;
        $command = new CommandTester(new ContinueCommand($runtime));

        $exit = $command->execute([
            '--working-dir' => $this->directory,
            '--no-tests' => true,
            '--no-interaction' => true,
        ]);

        if ($exit !== 0) {
            self::fail($command->getDisplay());
        }
        self::assertCount(1, $runtime->runs);
        self::assertSame(10, $runtime->runs[0]['plan']->currentMajor);
        self::assertSame(11, $runtime->runs[0]['plan']->targetMajor);
        self::assertSame('code', $runtime->runs[0]['plan']->fromStep);
        self::assertTrue($runtime->runs[0]['options']['noTests']);
        $state = $store->load();
        self::assertIsArray($state);
        $stateOptions = $state['options'] ?? null;
        self::assertIsArray($stateOptions);
        self::assertArrayNotHasKey('apiToken', $stateOptions);
        self::assertFalse($stateOptions['noTests'] ?? null);
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
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($directory);
    }

    /** @return array<string, string> */
    private function snapshotFiles(): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($this->directory) + 1));
            $snapshot[$relative] = (string) file_get_contents($fileInfo->getPathname());
        }

        ksort($snapshot);

        return $snapshot;
    }
}

final class RecordingRuntime implements UpgradeRuntimeInterface
{
    /** @var list<array{plan: UpgradePlan, directory: string, options: array<string, mixed>}> */
    public array $runs = [];

    public function __construct(private readonly ?UpgradeRunResult $result = null) {}

    public function run(
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
        ?UpgradeObserver $observer = null,
    ): UpgradeRunResult {
        $this->runs[] = ['plan' => $plan, 'directory' => $workingDirectory, 'options' => $options];
        $result = $this->result ?? UpgradeRunResult::successful([], []);

        return $result;
    }
}

final class ApplicationRecordingSingleStepRuntime implements SingleStepRuntimeInterface
{
    public function runStep(
        string $step,
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
    ): StepResult {
        unset($step, $plan, $workingDirectory, $options);

        return StepResult::successful();
    }
}

final class PlanNoOpStep implements StepInterface
{
    public function __construct(private readonly string $stepName) {}

    public function name(): string
    {
        return $this->stepName;
    }

    public function execute(UpgradeContext $context): StepResult
    {
        return StepResult::successful(message: $this->stepName.' previewed');
    }
}
