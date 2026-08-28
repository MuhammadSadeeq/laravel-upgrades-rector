<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

/** Runs one target-major verification pass. */
final class VerifyCommand extends SingleStepCommand
{
    protected function stepName(): string
    {
        return 'verify';
    }

    protected function commandDescription(): string
    {
        return 'Verify a Laravel upgrade transition for one major';
    }
}
