<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunResult;

/** Command-facing runtime seam for deterministic integration tests. */
interface UpgradeRuntimeInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function run(
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
        ?UpgradeObserver $observer = null,
    ): UpgradeRunResult;
}
