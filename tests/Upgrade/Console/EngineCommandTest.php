<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Application;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\AdviseCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\CodeCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\PostCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\SingleStepCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\SkeletonCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\VerifyCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\SingleStepRuntimeInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\UpgradeRuntimeFactory;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class EngineCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-upgrade-engine-'.bin2hex(random_bytes(5));
        mkdir($this->directory, 0777, true);
        file_put_contents($this->directory.'/composer.json', '{"require":{"laravel/framework":"^10.0"}}');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_application_registers_all_engine_commands(): void
    {
        $application = new Application(null, new RecordingSingleStepRuntime);

        foreach (['deps', 'skeleton', 'code', 'advise', 'post', 'verify'] as $name) {
            self::assertSame($name, $application->find($name)->getName());
        }
    }

    public function test_engine_command_runs_exactly_one_step_and_translates_common_options(): void
    {
        $runtime = new RecordingSingleStepRuntime;
        $command = new CommandTester(new CodeCommand($runtime));

        self::assertSame(0, $command->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
            '--plan' => true,
            '--composer' => 'tools/composer',
            '--no-tests' => true,
            '--no-pint' => true,
            '--annotate' => true,
            '--no-interaction' => true,
        ]));

        self::assertCount(1, $runtime->calls);
        self::assertSame('code', $runtime->calls[0]['step']);
        self::assertTrue($runtime->calls[0]['plan']->isPlanMode());
        self::assertSame('tools/composer', $runtime->calls[0]['options']['composerBinary'] ?? null);
        self::assertTrue($runtime->calls[0]['options']['noTests'] ?? false);
        self::assertTrue($runtime->calls[0]['options']['noPint'] ?? false);
        self::assertFalse($runtime->calls[0]['options']['annotate'] ?? true);
        self::assertFileDoesNotExist($this->directory.'/.laravel-upgrade/report.json');
    }

    public function test_engine_commands_reject_multi_major_targets_and_accept_noop_targets(): void
    {
        $runtime = new RecordingSingleStepRuntime;
        $command = new CommandTester(new PostCommand($runtime));

        self::assertSame(1, $command->execute([
            'target-major' => '13',
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]));
        self::assertSame([], $runtime->calls);

        file_put_contents($this->directory.'/composer.json', '{"require":{"laravel/framework":"^11.0"}}');
        $noop = new CommandTester(new PostCommand($runtime));

        self::assertSame(0, $noop->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
        ]));
        self::assertSame([], $runtime->calls);
    }

    public function test_factory_runs_one_step_and_records_apply_report_but_plan_is_byte_neutral(): void
    {
        $runtime = new UpgradeRuntimeFactory(
            stepFactory: static function (ProcessRunner $processRunner, BinaryResolver $binaryResolver, GitCheckpointService $git): array {
                unset($processRunner, $binaryResolver, $git);

                return [new EngineFixtureStep('code')];
            },
        );
        $plan = new UpgradePlan(10, 11);

        $result = $runtime->runStep('code', $plan, $this->directory);

        self::assertTrue($result->isSuccessful());
        self::assertFileExists($this->directory.'/.laravel-upgrade/report.json');
        $before = $this->snapshotFiles();
        $planResult = $runtime->runStep('code', new UpgradePlan(10, 11, true), $this->directory);

        self::assertTrue($planResult->isSuccessful());
        self::assertSame($before, $this->snapshotFiles());
    }

    public function test_factory_reuses_existing_report_history_and_validates_it_before_step_execution(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $plan = new UpgradePlan(10, 11);
        $generator->recordStep(new UpgradeContext($this->directory, $plan, 'full-run'), new StepExecutionResult(
            '10->11',
            10,
            11,
            'preflight',
            StepResult::successful(data: [
                'findings' => [[
                    'id' => 'history-1',
                    'ruleId' => 'laravelUpgrade.history',
                    'severity' => 'medium',
                    'laravelVersion' => 11,
                    'file' => 'composer.json',
                    'line' => 1,
                    'message' => 'Retain this history.',
                ]],
            ]),
        ));

        $runtime = new UpgradeRuntimeFactory(
            stepFactory: static function (ProcessRunner $processRunner, BinaryResolver $binaryResolver, GitCheckpointService $git): array {
                unset($processRunner, $binaryResolver, $git);

                return [new EngineFixtureStep('code')];
            },
        );

        self::assertTrue($runtime->runStep('code', $plan, $this->directory)->isSuccessful());
        $report = json_decode((string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'), true);
        self::assertIsArray($report);
        self::assertSame('full-run', $report['runId'] ?? null);
        $steps = $report['steps'] ?? null;
        self::assertIsArray($steps);
        self::assertCount(2, $steps);
        self::assertStringContainsString('Retain this history.', (string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'));

        file_put_contents($this->directory.'/.laravel-upgrade/report.json', '{broken');
        $result = $runtime->runStep('code', $plan, $this->directory);
        self::assertTrue($result->isFailed());
        self::assertSame(1, $result->exitCode);
    }

    public function test_factory_selects_each_requested_engine_step(): void
    {
        $runtime = new UpgradeRuntimeFactory(
            stepFactory: static function (ProcessRunner $processRunner, BinaryResolver $binaryResolver, GitCheckpointService $git): array {
                unset($processRunner, $binaryResolver, $git);

                return [
                    new EngineFixtureStep('code'),
                    new EngineFixtureStep('advisories'),
                    new EngineFixtureStep('verify'),
                ];
            },
        );
        $plan = new UpgradePlan(10, 11);

        foreach (['code', 'advisories', 'verify'] as $step) {
            self::assertTrue($runtime->runStep($step, $plan, $this->directory)->isSuccessful());
        }

        $report = json_decode((string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'), true);
        self::assertIsArray($report);
        $steps = $report['steps'] ?? null;
        self::assertIsArray($steps);
        self::assertSame(['code', 'advisories', 'verify'], array_column($steps, 'name'));
    }

    public function test_later_standalone_transition_extends_existing_report_bounds(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $generator->recordStep(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'shared-run',
        ), new StepExecutionResult(
            '10->11',
            10,
            11,
            'preflight',
            StepResult::successful(),
        ));
        $runtime = $this->fixtureRuntime('code');

        self::assertTrue($runtime->runStep('code', new UpgradePlan(11, 12), $this->directory)->isSuccessful());
        $report = json_decode((string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'), true);
        self::assertIsArray($report);
        $project = $report['project'] ?? null;
        self::assertIsArray($project);
        self::assertSame('10', $project['from'] ?? null);
        self::assertSame('12', $project['to'] ?? null);
        $steps = $report['steps'] ?? null;
        self::assertIsArray($steps);
        self::assertSame(['preflight', 'code'], array_column($steps, 'name'));
    }

    public function test_standalone_transition_never_shrinks_existing_report_target(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $generator->recordStep(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 13),
            'shared-run',
            activeFromMajor: 10,
            activeToMajor: 11,
        ), new StepExecutionResult(
            '10->11',
            10,
            11,
            'preflight',
            StepResult::successful(),
        ));
        $runtime = $this->fixtureRuntime('code');

        self::assertTrue($runtime->runStep('code', new UpgradePlan(10, 11), $this->directory)->isSuccessful());
        $report = json_decode((string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'), true);
        self::assertIsArray($report);
        $project = $report['project'] ?? null;
        self::assertIsArray($project);
        self::assertSame('10', $project['from'] ?? null);
        self::assertSame('13', $project['to'] ?? null);
    }

    public function test_engine_command_normalizes_raw_step_exit_codes(): void
    {
        $runtime = new RecordingSingleStepRuntime(StepResult::failed('raw process failure', exitCode: 9));
        $command = new CommandTester(new CodeCommand($runtime));

        self::assertSame(1, $command->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]));
    }

    #[DataProvider('engineCommandProvider')]
    public function test_engine_step_exit_codes_are_preserved(string $commandClass, string $expectedStep, int $exitCode): void
    {
        $runtime = new RecordingSingleStepRuntime(StepResult::failed('step failed', exitCode: $exitCode));
        /** @var SingleStepCommand $commandInstance */
        $commandInstance = new $commandClass($runtime);
        $command = new CommandTester($commandInstance);

        self::assertSame($exitCode, $command->execute([
            'target-major' => '11',
            '--working-dir' => $this->directory,
            '--no-interaction' => true,
        ]));
        self::assertSame($expectedStep, $runtime->calls[0]['step'] ?? null);
    }

    /** @return iterable<string, array{class-string<SingleStepCommand>, string, int}> */
    public static function engineCommandProvider(): iterable
    {
        yield 'skeleton' => [SkeletonCommand::class, 'skeleton', 4];
        yield 'code' => [CodeCommand::class, 'code', 1];
        yield 'advise' => [AdviseCommand::class, 'advisories', 1];
        yield 'post' => [PostCommand::class, 'post', 1];
        yield 'verify' => [VerifyCommand::class, 'verify', 1];
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

    /** @return array<string, string> */
    private function snapshotFiles(): array
    {
        $files = [];

        if (! is_dir($this->directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (! $fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getPathname();
            $relative = ltrim(substr($path, strlen($this->directory)), '/\\');
            $contents = file_get_contents($path);
            $files[$relative] = $contents === false ? '' : $contents;
        }

        ksort($files);

        return $files;
    }

    private function fixtureRuntime(string ...$steps): UpgradeRuntimeFactory
    {
        return new UpgradeRuntimeFactory(
            stepFactory: static function (ProcessRunner $processRunner, BinaryResolver $binaryResolver, GitCheckpointService $git) use ($steps): array {
                unset($processRunner, $binaryResolver, $git);

                return array_map(
                    static fn (string $step): EngineFixtureStep => new EngineFixtureStep($step),
                    $steps,
                );
            },
        );
    }
}

/** @internal */
final class RecordingSingleStepRuntime implements SingleStepRuntimeInterface
{
    /** @var list<array{step: string, plan: UpgradePlan, workingDirectory: string, options: array<string, mixed>}> */
    public array $calls = [];

    public function __construct(private readonly StepResult $result = new StepResult) {}

    public function runStep(string $step, UpgradePlan $plan, string $workingDirectory, array $options = []): StepResult
    {
        $this->calls[] = [
            'step' => $step,
            'plan' => $plan,
            'workingDirectory' => $workingDirectory,
            'options' => $options,
        ];

        return $this->result;
    }
}

/** @internal */
final class EngineFixtureStep implements StepInterface
{
    public function __construct(private readonly string $stepName) {}

    public function name(): string
    {
        return $this->stepName;
    }

    public function execute(UpgradeContext $context): StepResult
    {
        return StepResult::successful(message: 'fixture step completed', data: [
            'target' => $context->toMajor(),
        ]);
    }
}
