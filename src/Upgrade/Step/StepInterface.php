<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step;

/**
 * Contract for one independently journaled upgrade step.
 */
interface StepInterface
{
    public function name(): string;

    public function execute(UpgradeContext $context): StepResult;
}
