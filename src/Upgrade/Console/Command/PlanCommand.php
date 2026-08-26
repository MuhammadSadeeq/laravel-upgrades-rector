<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the upgrade plan without touching anything (plan P4-01).
 * Thin wrapper around `to --dry-run` for explicit intent.
 */
final class PlanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('plan')
            ->setDescription('Print the upgrade plan without touching anything')
            ->addArgument('target-major', InputArgument::REQUIRED, 'Target Laravel major version')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            return Command::FAILURE;
        }

        $targetRaw = $input->getArgument('target-major');
        $targetMajorString = is_scalar($targetRaw) ? (string) $targetRaw : '';

        $dirOption = $input->getOption('working-dir');
        $workingDir = is_string($dirOption) && $dirOption !== '' ? $dirOption : '.';

        $toInput = new ArrayInput([
            'command' => 'to',
            'target-major' => $targetMajorString,
            '--working-dir' => $workingDir,
            '--dry-run' => true,
        ]);

        return $application->find('to')->run($toInput, $output);
    }
}
