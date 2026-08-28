<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

/** Runs one target-major Rector code transformation. */
final class CodeCommand extends SingleStepCommand
{
    protected function stepName(): string
    {
        return 'code';
    }

    protected function commandDescription(): string
    {
        return 'Apply (or preview) Laravel code transformations for one major';
    }
}
