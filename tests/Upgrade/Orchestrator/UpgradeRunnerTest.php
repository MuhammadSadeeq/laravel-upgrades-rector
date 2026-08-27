<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Orchestrator;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UpgradeRunnerTest extends TestCase
{
    private string $workingDirectory;

    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir().'/laravel-upgrade-runner-'.bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workingDirectory);
    }

    public function test_multi_major_run_is_strictly_ordered_and_context_is_transition_specific(): void
    {
        $steps = $this->steps();
        $observer = new RecordingObserver;
        $runner = new UpgradeRunner(new StateStore($this->workingDirectory), $steps, $observer);

        $result = $runner->run(new UpgradePlan(10, 13), ['constraintPolicy' => 'widen']);

        self::assertTrue($result->success);
        self::assertSame(['10->11', '11->12', '12->13'], $result->completedTransitions);
        self::assertSame(27, count($result->stepResults));

        foreach ($result->stepResults as $index => $stepResult) {
            $expectedStep = UpgradePlan::canonicalStepNames()[$index % 9];
            $expectedFrom = 10 + intdiv($index, 9);

            self::assertSame($expectedStep, $stepResult->step);
            self::assertSame($expectedFrom, $stepResult->fromMajor);
            self::assertSame($expectedFrom + 1, $stepResult->toMajor);
        }

        self::assertSame(27, count($observer->completed));
        self::assertSame([10, 11, 12], array_values(array_unique(array_map(
            static fn (array $context): int => $context['from'],
            $observer->started,
        ))));
        self::assertSame('widen', $steps[0]->contexts[0]['options']['constraintPolicy']);
    }

    public function test_from_step_applies_only_to_first_transition(): void
    {
        $steps = $this->steps();
        $result = (new UpgradeRunner(new StateStore($this->workingDirectory), $steps))
            ->run(new UpgradePlan(10, 12, false, 'code'));

        self::assertTrue($result->success);
        self::assertSame(18, count($result->stepResults));
        self::assertSame(['preflight', 'dependencies', 'install'], array_map(
            static fn (StepExecutionResult $execution): string => $execution->step,
            array_slice($result->stepResults, 0, 3),
        ));
        self::assertContainsOnlyInstancesOf(StepExecutionResult::class, $result->stepResults);

        foreach (array_slice($result->stepResults, 0, 3) as $execution) {
            self::assertTrue($execution->result->isSkipped());
        }

        self::assertSame('preflight', $result->stepResults[9]->step);
        self::assertTrue($result->stepResults[9]->result->isSkipped() === false);
        self::assertSame(1, $steps[0]->calls);
        self::assertSame(1, $steps[1]->calls);
        self::assertSame(1, $steps[2]->calls);
        self::assertSame(2, $steps[4]->calls);
        self::assertSame([10, 11], array_values(array_unique(array_map(
            static fn (array $context): int => $context['from'],
            $steps[4]->contexts,
        ))));
    }

    public function test_global_skips_are_journaled_and_do_not_execute_steps(): void
    {
        $steps = $this->steps();
        $store = new StateStore($this->workingDirectory);
        $result = (new UpgradeRunner($store, $steps))->run(new UpgradePlan(10, 11, false, null, 'skeleton,commit'));

        self::assertTrue($result->success);
        self::assertTrue($result->stepResults[3]->result->isSkipped());
        self::assertTrue($result->stepResults[8]->result->isSkipped());
        self::assertSame(0, $steps[3]->calls);
        self::assertSame(0, $steps[8]->calls);
        self::assertNull($store->load());
    }

    public function test_failure_is_journaled_and_resume_reruns_only_the_failed_step(): void
    {
        $store = new StateStore($this->workingDirectory);
        $failingSteps = $this->steps('dependencies');
        $failed = (new UpgradeRunner($store, $failingSteps))->run(new UpgradePlan(10, 11));

        self::assertTrue($failed->isFailure());
        self::assertSame('dependencies', $failed->failedStep);
        self::assertSame('10->11', $failed->failedTransition);
        self::assertSame(3, $failed->exitCode);
        self::assertSame('dependencies failed', $failed->failureMessage);
        self::assertSame(1, $failingSteps[0]->calls);
        self::assertSame(1, $failingSteps[1]->calls);

        $state = $store->load();
        self::assertIsArray($state);
        self::assertSame('failed', $state['status']);
        self::assertSame('dependencies', $state['failedStep']);
        self::assertSame('dependencies', $store->firstIncompleteStep(new UpgradePlan(10, 11)));

        $resumedSteps = $this->steps();
        $resumed = (new UpgradeRunner($store, $resumedSteps))->run(new UpgradePlan(10, 11));

        self::assertTrue($resumed->success);
        self::assertSame(['10->11'], $resumed->completedTransitions);
        self::assertSame(0, $resumedSteps[0]->calls);
        self::assertSame(1, $resumedSteps[1]->calls);
        self::assertNull($store->load());
    }

    public function test_resume_at_a_later_transition_merges_stored_options_and_overrides(): void
    {
        $store = new StateStore($this->workingDirectory);
        $firstSteps = $this->steps(failureStep: 'code', failureFrom: 11);

        $failed = (new UpgradeRunner($store, $firstSteps))->run(
            new UpgradePlan(10, 13),
            [
                'composerBinary' => '/usr/local/bin/composer',
                'noTests' => false,
                'apiToken' => 'must-not-be-persisted',
            ],
        );

        self::assertTrue($failed->isFailure());
        self::assertSame('11->12', $failed->failedTransition);
        self::assertSame(['10->11'], $failed->completedTransitions);

        $state = $store->load();
        self::assertIsArray($state);
        self::assertSame(11, $state['currentMajor'] ?? null);
        self::assertSame('11->12', $state['currentTransition'] ?? null);
        $storedOptions = $state['options'] ?? null;
        self::assertIsArray($storedOptions);
        self::assertSame('/usr/local/bin/composer', $storedOptions['composerBinary'] ?? null);
        self::assertFalse($storedOptions['noTests'] ?? true);
        self::assertArrayNotHasKey('apiToken', $storedOptions);

        $resumedSteps = $this->steps();
        $resumed = (new UpgradeRunner($store, $resumedSteps))->run(
            new UpgradePlan(11, 13),
            ['noTests' => true],
        );

        self::assertTrue($resumed->success);
        self::assertSame(['11->12', '12->13'], $resumed->completedTransitions);
        self::assertNull($store->load());
        self::assertNotEmpty($resumedSteps[4]->contexts);
        self::assertSame(11, $resumedSteps[4]->contexts[0]['from']);
        self::assertSame('/usr/local/bin/composer', $resumedSteps[4]->contexts[0]['options']['composerBinary'] ?? null);
        self::assertTrue($resumedSteps[4]->contexts[0]['options']['noTests'] ?? false);
    }

    public function test_exception_is_converted_to_a_failed_result_with_step_exit_code(): void
    {
        $steps = $this->steps(exceptionStep: 'preflight');
        $result = (new UpgradeRunner(new StateStore($this->workingDirectory), $steps))
            ->run(new UpgradePlan(10, 11));

        self::assertTrue($result->isFailure());
        self::assertSame(2, $result->exitCode);
        self::assertSame('preflight exploded', $result->stepResults[0]->result->message);
        self::assertTrue($result->stepResults[0]->result->isFailed());
    }

    public function test_step_provided_failure_exit_code_is_preserved(): void
    {
        $steps = $this->steps(failureCode: 17, failureStep: 'install');
        $result = (new UpgradeRunner(new StateStore($this->workingDirectory), $steps))
            ->run(new UpgradePlan(10, 11));

        self::assertFalse($result->success);
        self::assertSame(17, $result->exitCode);
    }

    public function test_plan_mode_executes_preview_without_creating_state_files(): void
    {
        $store = new StateStore($this->workingDirectory, true);
        $result = (new UpgradeRunner($store, $this->steps()))->run(new UpgradePlan(10, 12, true));

        self::assertTrue($result->success);
        self::assertSame(18, count($result->stepResults));
        self::assertDirectoryDoesNotExist($store->directory());
        self::assertFileDoesNotExist($store->path());
    }

    public function test_observer_exceptions_do_not_fail_or_unjournal_successful_work(): void
    {
        $store = new StateStore($this->workingDirectory);
        $result = (new UpgradeRunner($store, $this->steps(), new ThrowingObserver))
            ->run(new UpgradePlan(10, 11));

        self::assertTrue($result->success);
        self::assertNull($store->load());
    }

    public function test_duplicate_step_names_are_rejected(): void
    {
        $steps = $this->steps();
        $steps[] = new RecordingStep('preflight');

        $this->expectException(InvalidArgumentException::class);
        (new UpgradeRunner(new StateStore($this->workingDirectory), $steps))->run(new UpgradePlan(10, 11));
    }

    public function test_missing_canonical_step_is_rejected(): void
    {
        $steps = array_slice($this->steps(), 0, -1);

        $this->expectException(InvalidArgumentException::class);
        (new UpgradeRunner(new StateStore($this->workingDirectory), $steps))->run(new UpgradePlan(10, 11));
    }

    /**
     * @return list<RecordingStep>
     */
    private function steps(
        ?string $failureStep = null,
        ?string $exceptionStep = null,
        ?int $failureCode = null,
        ?int $failureFrom = null,
    ): array {
        $steps = [];

        foreach (UpgradePlan::canonicalStepNames() as $name) {
            $steps[] = new RecordingStep(
                $name,
                $failureStep === $name,
                $exceptionStep === $name,
                $failureCode,
                $failureFrom,
            );
        }

        return $steps;
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
}

final class RecordingStep implements StepInterface
{
    public int $calls = 0;

    /** @var list<array{from: int, to: int, options: array<string, mixed>}> */
    public array $contexts = [];

    public function __construct(
        private readonly string $stepName,
        private readonly bool $fails = false,
        private readonly bool $throws = false,
        private readonly ?int $failureCode = null,
        private readonly ?int $failureFrom = null,
    ) {}

    public function name(): string
    {
        return $this->stepName;
    }

    public function execute(UpgradeContext $context): StepResult
    {
        $this->calls++;
        $this->contexts[] = [
            'from' => $context->fromMajor(),
            'to' => $context->toMajor(),
            'options' => $context->options,
        ];

        if ($this->throws) {
            throw new RuntimeException($this->stepName.' exploded');
        }

        if ($this->fails && ($this->failureFrom === null || $this->failureFrom === $context->fromMajor())) {
            return StepResult::failed($this->stepName.' failed', exitCode: $this->failureCode);
        }

        return StepResult::successful(message: $this->stepName.' completed');
    }
}

final class RecordingObserver implements UpgradeObserver
{
    /** @var list<array{transition: string, step: string, from: int}> */
    public array $started = [];

    /** @var list<array{transition: string, step: string}> */
    public array $completed = [];

    public function stepStarted(string $transition, string $step, UpgradeContext $context): void
    {
        $this->started[] = [
            'transition' => $transition,
            'step' => $step,
            'from' => $context->fromMajor(),
        ];
    }

    public function stepCompleted(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void {
        $this->completed[] = ['transition' => $transition, 'step' => $step];
    }

    public function stepFailed(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void {}
}

final class ThrowingObserver implements UpgradeObserver
{
    public function stepStarted(string $transition, string $step, UpgradeContext $context): void
    {
        throw new RuntimeException('renderer started failure');
    }

    public function stepCompleted(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void {
        throw new RuntimeException('renderer completed failure');
    }

    public function stepFailed(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void {}
}
