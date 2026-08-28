<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use Closure;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\PhpStanConfigGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrix;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerProcessAdapter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ConstraintPlanner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ManifestReader;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\SymfonyProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Rector\RectorConfigGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\AdvisoryStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\CodeStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\CommitStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\DependencyStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\InstallStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\PostStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\PreflightStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\SkeletonSyncStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext as StepUpgradeContext;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\VerifyStep;

/**
 * Default dependency graph for `to`, `plan`, and `continue`.
 *
 * A factory owns one process runner. Every real step and the checkpoint
 * service receives that same runner, which keeps argv execution injectable
 * and makes command integration tests deterministic.
 */
final class UpgradeRuntimeFactory implements SingleStepRuntimeInterface, UpgradeRuntimeFactoryInterface, UpgradeRuntimeInterface
{
    private readonly ProcessRunner $processRunner;

    private readonly BinaryResolver $binaryResolver;

    /** @var null|Closure(ProcessRunner, BinaryResolver, GitCheckpointService): iterable<StepInterface> */
    private readonly ?Closure $stepFactory;

    /**
     * @param  null|callable(ProcessRunner, BinaryResolver, GitCheckpointService): iterable<StepInterface>  $stepFactory
     */
    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?BinaryResolver $binaryResolver = null,
        ?callable $stepFactory = null,
    ) {
        $this->processRunner = $processRunner ?? new SymfonyProcessRunner;
        $this->binaryResolver = $binaryResolver ?? new BinaryResolver;
        $this->stepFactory = $stepFactory === null ? null : Closure::fromCallable($stepFactory);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createRunner(
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
        ?UpgradeObserver $observer = null,
    ): UpgradeRunner {
        $git = new GitCheckpointService($this->processRunner, $this->binaryResolver);
        $report = new UpgradeReportGenerator;
        $steps = $this->stepFactory === null
            ? $this->realSteps($git, $report)
            : ($this->stepFactory)($this->processRunner, $this->binaryResolver, $git);

        return new UpgradeRunner(
            new StateStore($workingDirectory, $plan->isPlanMode()),
            $steps,
            $observer,
            $git,
            $report,
        );
    }

    /** @param array<string, mixed> $options */
    public function run(
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
        ?UpgradeObserver $observer = null,
    ): UpgradeRunResult {
        return $this->createRunner($plan, $workingDirectory, $options, $observer)->run($plan, $options);
    }

    /**
     * Execute one engine step without creating a StateStore journal or a git
     * checkpoint. Apply-mode results are still recorded in the canonical
     * report so the step can be reviewed alongside a full run.
     *
     * @param  array<string, mixed>  $options
     */
    public function runStep(
        string $step,
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
    ): StepResult {
        if (! $plan->isNoOp() && count($plan->transitions()) !== 1) {
            throw new \InvalidArgumentException('An engine command requires exactly one major transition.');
        }

        $git = new GitCheckpointService($this->processRunner, $this->binaryResolver);
        $report = new UpgradeReportGenerator;
        $steps = $this->stepFactory === null
            ? $this->realSteps($git, $report)
            : ($this->stepFactory)($this->processRunner, $this->binaryResolver, $git);
        $selected = null;

        foreach ($steps as $candidate) {
            if ($candidate->name() === $step) {
                $selected = $candidate;

                break;
            }
        }

        if (! $selected instanceof StepInterface) {
            throw new \InvalidArgumentException(sprintf('Unknown engine step "%s".', $step));
        }

        $fromMajor = $plan->currentMajor;
        $toMajor = $plan->targetMajor;
        $fallbackRunId = is_string($options['runId'] ?? null) && $options['runId'] !== ''
            ? $options['runId']
            : sprintf('engine-%d-%d', $fromMajor, $toMajor);

        try {
            // Validate an existing canonical report before a mutating step is
            // allowed to run. Missing reports use a stable standalone id so
            // repeated engine invocations replace their own step entry.
            $runId = $plan->isPlanMode()
                ? $fallbackRunId
                : $report->runIdFor($workingDirectory, $fallbackRunId);
        } catch (\Throwable $exception) {
            return StepResult::failed(
                message: 'The existing canonical upgrade report is invalid: '.$exception->getMessage(),
                data: ['check' => 'canonical-report', 'reportError' => $exception->getMessage()],
                exitCode: $this->stepFailureCode($step),
            );
        }

        $context = new StepUpgradeContext(
            $workingDirectory,
            $plan,
            $runId,
            $options,
            activeFromMajor: $fromMajor,
            activeToMajor: $toMajor,
        );

        try {
            $result = $selected->execute($context);
        } catch (\Throwable $exception) {
            $result = StepResult::failed(
                message: $exception->getMessage(),
                exitCode: $this->stepFailureCode($step),
            );
        }

        if ($plan->isPlanMode()) {
            return $result;
        }

        try {
            $report->recordStep($context, new StepExecutionResult(
                UpgradePlan::transitionLabel($fromMajor, $toMajor),
                $fromMajor,
                $toMajor,
                $step,
                $result,
            ));
        } catch (\Throwable $exception) {
            $data = $result->data;
            $data['reportError'] = $exception->getMessage();

            return StepResult::failed(
                message: $result->message !== '' ? $result->message : 'The step completed but its report could not be written.',
                changedFiles: $result->changedFiles,
                findingsCount: $result->findingsCount,
                data: $data,
                exitCode: $result->exitCode ?? $this->stepFailureCode($step),
            );
        }

        return $result;
    }

    /** @return list<StepInterface> */
    private function realSteps(GitCheckpointService $git, UpgradeReportGenerator $report): array
    {
        $root = dirname(__DIR__, 3);
        $manifestReader = new ManifestReader;
        $matrix = new CompatibilityMatrix($root.'/resources/compat/packages.json');
        $planner = new ConstraintPlanner($matrix, $root.'/resources/compat/removals.json');
        $composer = new ComposerProcessAdapter($this->processRunner, $this->binaryResolver);

        return [
            new PreflightStep($this->processRunner, $root.'/resources/compat/php.json', $this->binaryResolver),
            new DependencyStep($planner, $composer, $manifestReader),
            new InstallStep($composer),
            new SkeletonSyncStep(new SkeletonStep),
            new CodeStep($this->processRunner, new RectorConfigGenerator),
            new AdvisoryStep($this->processRunner, new PhpStanConfigGenerator),
            new PostStep($this->processRunner, $root.'/resources/compat/post-install-steps.json', $this->binaryResolver),
            new VerifyStep($this->processRunner, $this->binaryResolver),
            new CommitStep($git, $report),
        ];
    }

    private function stepFailureCode(string $step): int
    {
        return match ($step) {
            'preflight' => 2,
            'dependencies' => 3,
            'skeleton' => 4,
            default => 1,
        };
    }
}
