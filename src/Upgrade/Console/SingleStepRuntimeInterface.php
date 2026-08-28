<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;

/**
 * Command-facing seam for running one real upgrade step without a journal.
 *
 * Engine commands intentionally do not resume or commit a complete upgrade;
 * they invoke this seam and let the concrete runtime handle report
 * persistence in apply mode.
 */
interface SingleStepRuntimeInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function runStep(
        string $step,
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
    ): StepResult;
}
