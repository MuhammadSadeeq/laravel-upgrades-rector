<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;

/**
 * One ordered step outcome, annotated with its concrete major transition.
 */
final class StepExecutionResult
{
    public function __construct(
        public readonly string $transition,
        public readonly int $fromMajor,
        public readonly int $toMajor,
        public readonly string $step,
        public readonly StepResult $result,
    ) {}

    public function isSkipped(): bool
    {
        return $this->result->isSkipped();
    }
}
