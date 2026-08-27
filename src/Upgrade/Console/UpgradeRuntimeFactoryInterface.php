<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunner;

/** Creates the complete real step graph for a command invocation. */
interface UpgradeRuntimeFactoryInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function createRunner(
        UpgradePlan $plan,
        string $workingDirectory,
        array $options = [],
        ?UpgradeObserver $observer = null,
    ): UpgradeRunner;
}
