<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

/** Runs one target-major PHPStan advisory pass. */
final class AdviseCommand extends SingleStepCommand
{
    protected function stepName(): string
    {
        return 'advisories';
    }

    protected function commandName(): string
    {
        return 'advise';
    }

    protected function commandDescription(): string
    {
        return 'Run Laravel upgrade advisories for one target major';
    }
}
