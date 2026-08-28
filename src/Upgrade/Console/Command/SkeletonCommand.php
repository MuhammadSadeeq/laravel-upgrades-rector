<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

/** Runs one Laravel skeleton/config synchronization transition. */
final class SkeletonCommand extends SingleStepCommand
{
    protected function stepName(): string
    {
        return 'skeleton';
    }

    protected function commandDescription(): string
    {
        return 'Synchronize the Laravel skeleton for one major transition';
    }
}
