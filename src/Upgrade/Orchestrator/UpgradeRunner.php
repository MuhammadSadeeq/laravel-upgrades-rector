<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use Throwable;

/**
 * Executes validated upgrade steps in strict, journaled transition order.
 */
final class UpgradeRunner
{
    /** @var list<StepInterface> */
    private array $steps;

    /**
     * @param  iterable<StepInterface>  $steps
     */
    public function __construct(
        private readonly StateStore $stateStore,
        iterable $steps,
        private readonly ?UpgradeObserver $observer = null,
        private readonly ?GitCheckpointService $gitCheckpoint = null,
    ) {
        $this->steps = [];

        foreach ($steps as $step) {
            if (! $step instanceof StepInterface) {
                throw new InvalidArgumentException('Upgrade runner steps must implement StepInterface.');
            }

            $this->steps[] = $step;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function run(UpgradePlan $plan, array $options = []): UpgradeRunResult
    {
        $stepsByName = $this->validatedSteps();
        $state = $this->stateStore->start($plan, options: $options);
        $options = StateStore::mergeOptions(
            StateStore::optionsFromState($state['options'] ?? null),
            $options,
        );

        // Verification needs the aggregate paths recorded by earlier real
        // steps. Keep these transient in the context; they are journal data,
        // not user-supplied run options.
        $changedFiles = $state['changedFiles'] ?? [];

        if (is_array($changedFiles)) {
            $safeChangedFiles = [];

            foreach ($changedFiles as $changedFile) {
                if (is_string($changedFile)) {
                    $safeChangedFiles[] = $changedFile;
                }
            }

            $options['changedFiles'] = $safeChangedFiles;
        }
        $runId = $this->stateString($state, 'runId');

        if ($plan->isNoOp()) {
            return UpgradeRunResult::successful([], []);
        }

        $results = [];
        $completedTransitions = $this->completedTransitions($plan, $state);

        foreach ($plan->transitions() as $targetMajor) {
            $transition = UpgradePlan::transitionLabel($targetMajor - 1, $targetMajor);
            $state = $this->stateStore->load() ?? $state;

            if ($this->stateString($state, 'status') === StateStore::STATUS_COMPLETED) {
                break;
            }

            $currentMajor = $this->stateInt($state, 'currentMajor');

            if ($targetMajor <= $currentMajor) {
                if (! in_array($transition, $completedTransitions, true)) {
                    $completedTransitions[] = $transition;
                }

                continue;
            }

            $activeTransition = $this->stateNullableString($state, 'currentTransition');

            if ($activeTransition !== $transition) {
                throw new InvalidArgumentException(sprintf(
                    'The journal is active at transition %s, not %s.',
                    $activeTransition ?? '(none)',
                    $transition,
                ));
            }

            $context = new UpgradeContext(
                workingDirectory: $this->stateStore->workingDirectory(),
                plan: $plan,
                runId: $runId,
                options: $options,
                activeFromMajor: $targetMajor - 1,
                activeToMajor: $targetMajor,
            );
            try {
                $this->gitCheckpoint?->prepare($context);
            } catch (Throwable) {
                // The checkpoint is retried around the first checkpointed
                // step, where a failure can be journaled against that step.
            }
            $selectedSteps = $plan->stepsForTransition($targetMajor);
            $completed = $this->completedStepsForTransition($state, $transition);

            // Journal all steps omitted by --from-step or --skip-step before
            // running the selected sequence. This is important when commit is
            // skipped: verify remains the selected final step that advances
            // the transition.
            foreach ($plan->canonicalSteps() as $stepName) {
                if (in_array($stepName, $selectedSteps, true) && ! in_array($stepName, $plan->skipSteps, true)) {
                    continue;
                }

                if (array_key_exists($stepName, $completed)) {
                    continue;
                }

                $state = $this->stateStore->recordSkippedStep(
                    $plan,
                    $stepName,
                    in_array($stepName, $plan->skipSteps, true)
                        ? 'Skipped by plan.'
                        : 'Skipped before the requested from-step.',
                );
                $completed = $this->completedStepsForTransition($state, $transition);
            }

            foreach ($plan->canonicalSteps() as $stepName) {
                if (! in_array($stepName, $selectedSteps, true) || in_array($stepName, $plan->skipSteps, true)) {
                    $skippedResult = StepResult::skipped(
                        in_array($stepName, $plan->skipSteps, true)
                            ? 'Skipped by plan.'
                            : 'Skipped before the requested from-step.',
                    );
                    $results[] = new StepExecutionResult(
                        transition: $transition,
                        fromMajor: $targetMajor - 1,
                        toMajor: $targetMajor,
                        step: $stepName,
                        result: $skippedResult,
                    );
                    try {
                        $this->observer?->stepCompleted($transition, $stepName, $context, $skippedResult);
                    } catch (Throwable) {
                        // A presentation callback must not make a deliberately
                        // skipped journal entry look unfinished.
                    }

                    continue;
                }

                if (array_key_exists($stepName, $completed)) {
                    continue;
                }

                $step = $stepsByName[$stepName];
                try {
                    $this->observer?->stepStarted($transition, $stepName, $context);
                } catch (Throwable) {
                    // Observers are presentation hooks. A broken renderer
                    // must not change the outcome of upgrade work.
                }

                try {
                    $result = $step->execute($context);
                } catch (Throwable $exception) {
                    $result = StepResult::failed(
                        $this->exceptionMessage($exception),
                        exitCode: $this->defaultExitCode($stepName),
                    );
                }

                $execution = new StepExecutionResult(
                    transition: $transition,
                    fromMajor: $targetMajor - 1,
                    toMajor: $targetMajor,
                    step: $stepName,
                    result: $result,
                );
                $results[] = $execution;

                if ($result->isFailed()) {
                    $this->stateStore->recordFailedStep($plan, $stepName, $result->message);
                    $this->notifyStepFailed($transition, $stepName, $context, $result);

                    return UpgradeRunResult::failed(
                        failedStep: $stepName,
                        failedTransition: $transition,
                        exitCode: $result->exitCode ?? $this->defaultExitCode($stepName),
                        stepResults: $results,
                        completedTransitions: $completedTransitions,
                        failureMessage: $result->message,
                    );
                }

                if ($this->gitCheckpoint !== null && in_array($stepName, ['dependencies', 'install', 'skeleton', 'code', 'post'], true)) {
                    $checkpointMessage = null;
                    try {
                        $checkpoint = $this->gitCheckpoint->afterStep($stepName, $context, $result);
                    } catch (Throwable $exception) {
                        $checkpoint = null;
                        $checkpointMessage = 'Git checkpoint failed: '.$this->exceptionMessage($exception);
                    }

                    $checkpointFailed = $checkpoint === null || $checkpoint->isFailed();

                    if ($checkpointFailed) {
                        $checkpointMessage ??= 'Git checkpoint failed: '.$checkpoint?->message;
                        $result = StepResult::failed(
                            message: $checkpointMessage,
                            data: $checkpoint === null ? ['check' => 'git-checkpoint'] : ['git' => $checkpoint->data],
                            exitCode: $checkpoint === null ? 1 : ($checkpoint->exitCode ?? 1),
                        );
                        unset($checkpointMessage);
                        $execution = new StepExecutionResult(
                            transition: $transition,
                            fromMajor: $targetMajor - 1,
                            toMajor: $targetMajor,
                            step: $stepName,
                            result: $result,
                        );
                        $results[array_key_last($results)] = $execution;
                        $this->stateStore->recordFailedStep($plan, $stepName, $result->message);
                        $this->notifyStepFailed($transition, $stepName, $context, $result);

                        return UpgradeRunResult::failed(
                            failedStep: $stepName,
                            failedTransition: $transition,
                            exitCode: $result->exitCode ?? 1,
                            stepResults: $results,
                            completedTransitions: $completedTransitions,
                            failureMessage: $result->message,
                        );
                    }
                }

                $state = $this->stateStore->recordCompletedStep(
                    $plan,
                    $stepName,
                    $result->changedFiles,
                    $result->findingsCount,
                );
                $completed = $this->completedStepsForTransition($state, $transition);

                try {
                    $this->observer?->stepCompleted($transition, $stepName, $context, $result);
                } catch (Throwable) {
                    // Observers are presentation hooks and are intentionally
                    // unable to turn a journaled success into a failure.
                }
            }

            if (! in_array($transition, $completedTransitions, true)) {
                $completedTransitions[] = $transition;
            }

            if ($this->stateString($state, 'status') === StateStore::STATUS_COMPLETED) {
                break;
            }
        }

        return UpgradeRunResult::successful($results, $completedTransitions);
    }

    /**
     * @return array<string, StepInterface>
     */
    private function validatedSteps(): array
    {
        $stepsByName = [];

        foreach ($this->steps as $step) {
            $name = $step->name();

            if ($name === '') {
                throw new InvalidArgumentException('Upgrade step names cannot be empty.');
            }

            if (array_key_exists($name, $stepsByName)) {
                throw new InvalidArgumentException(sprintf('Duplicate upgrade step "%s".', $name));
            }

            $stepsByName[$name] = $step;
        }

        foreach (UpgradePlan::canonicalStepNames() as $name) {
            if (! array_key_exists($name, $stepsByName)) {
                throw new InvalidArgumentException(sprintf('Missing implementation for upgrade step "%s".', $name));
            }
        }

        return $stepsByName;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function completedTransitions(UpgradePlan $plan, array $state): array
    {
        $completed = [];

        foreach ($plan->transitions() as $targetMajor) {
            $transition = UpgradePlan::transitionLabel($targetMajor - 1, $targetMajor);
            $entries = $this->completedStepsForTransition($state, $transition);
            $all = true;

            foreach ($plan->stepsForTransition($targetMajor) as $step) {
                if (! array_key_exists($step, $entries)) {
                    $all = false;
                    break;
                }
            }

            if ($all) {
                $completed[] = $transition;
            }
        }

        return $completed;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function completedStepsForTransition(array $state, string $transition): array
    {
        $completedSteps = $state['completedSteps'] ?? null;

        if (! is_array($completedSteps)) {
            return [];
        }

        $entries = $completedSteps[$transition] ?? null;

        if (! is_array($entries)) {
            return [];
        }

        $stringKeyEntries = [];

        foreach ($entries as $key => $entry) {
            if (is_string($key)) {
                $stringKeyEntries[$key] = $entry;
            }
        }

        return $stringKeyEntries;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function stateString(array $state, string $key): string
    {
        $value = $state[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('The upgrade journal has no valid %s.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function stateNullableString(array $state, string $key): ?string
    {
        $value = $state[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException(sprintf('The upgrade journal has no valid %s.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function stateInt(array $state, string $key): int
    {
        $value = $state[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('The upgrade journal has no valid %s.', $key));
        }

        return $value;
    }

    private function defaultExitCode(string $step): int
    {
        return match ($step) {
            'preflight' => 2,
            'dependencies', 'install' => 3,
            'skeleton' => 4,
            default => 1,
        };
    }

    private function exceptionMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== '' ? $message : $exception::class;
    }

    private function notifyStepFailed(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void {
        try {
            $this->observer?->stepFailed($transition, $step, $context, $result);
        } catch (Throwable) {
            // Observer failures must not hide the actual step failure.
        }
    }
}
