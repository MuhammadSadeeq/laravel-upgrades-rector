<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

/** Runs one target-major post-install action set. */
final class PostCommand extends SingleStepCommand
{
    protected function stepName(): string
    {
        return 'post';
    }

    protected function commandDescription(): string
    {
        return 'Run Laravel upgrade post-install actions for one major';
    }
}
