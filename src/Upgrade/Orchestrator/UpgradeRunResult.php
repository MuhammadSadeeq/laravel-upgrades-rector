<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator;

/**
 * Immutable result of an upgrade runner invocation.
 */
final class UpgradeRunResult
{
    /**
     * @param  list<StepExecutionResult>  $stepResults
     * @param  list<string>  $completedTransitions
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $failedStep,
        public readonly ?string $failedTransition,
        public readonly int $exitCode,
        public readonly array $stepResults,
        public readonly array $completedTransitions,
        public readonly ?string $failureMessage = null,
    ) {}

    /**
     * @param  list<StepExecutionResult>  $stepResults
     * @param  list<string>  $completedTransitions
     */
    public static function successful(array $stepResults, array $completedTransitions): self
    {
        return new self(true, null, null, 0, $stepResults, $completedTransitions);
    }

    /**
     * @param  list<StepExecutionResult>  $stepResults
     * @param  list<string>  $completedTransitions
     */
    public static function failed(
        string $failedStep,
        string $failedTransition,
        int $exitCode,
        array $stepResults,
        array $completedTransitions,
        ?string $failureMessage = null,
    ): self {
        return new self(false, $failedStep, $failedTransition, $exitCode, $stepResults, $completedTransitions, $failureMessage);
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }
}
