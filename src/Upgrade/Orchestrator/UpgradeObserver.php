<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;

/**
 * Optional presentation hook for command adapters and CI integrations.
 */
interface UpgradeObserver
{
    public function stepStarted(string $transition, string $step, UpgradeContext $context): void;

    public function stepCompleted(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void;

    public function stepFailed(
        string $transition,
        string $step,
        UpgradeContext $context,
        StepResult $result,
    ): void;
}
