<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeObserver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use Symfony\Component\Console\Output\OutputInterface;

/** Minimal console presentation hook; runner owns error isolation. */
final class ConsoleUpgradeObserver implements UpgradeObserver
{
    public function __construct(private readonly OutputInterface $output) {}

    public function stepStarted(string $transition, string $step, UpgradeContext $context): void
    {
        $this->output->writeln(sprintf('<info>[%s]</info> %s', $transition, $step));
    }

    public function stepCompleted(string $transition, string $step, UpgradeContext $context, StepResult $result): void
    {
        $label = $result->isSkipped() ? 'skipped' : 'done';
        $this->output->writeln(sprintf('  <comment>%s</comment>', $label));
    }

    public function stepFailed(string $transition, string $step, UpgradeContext $context, StepResult $result): void
    {
        $this->output->writeln(sprintf('  <error>failed: %s</error>', $result->message));
    }
}
