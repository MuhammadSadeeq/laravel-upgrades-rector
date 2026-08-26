<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Resumes an interrupted upgrade from the last successful step.
 * Reads .laravel-upgrade/state.json to determine what was completed.
 */
final class ContinueCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('continue')
            ->setDescription('Resume an interrupted upgrade from the last successful step')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $dirOption = $input->getOption('working-dir');
        $workingDirectory = is_string($dirOption) && $dirOption !== '' ? $dirOption : '.';

        $stateFile = rtrim($workingDirectory, '/').'/.laravel-upgrade/state.json';

        if (! is_file($stateFile)) {
            $style->error('No interrupted upgrade found (.laravel-upgrade/state.json missing).');
            $style->info('Use `to <major>` to start a fresh upgrade.');

            return Command::FAILURE;
        }

        /** @var array<string, mixed>|null $state */
        $state = json_decode((string) file_get_contents($stateFile), true);

        if (! is_array($state)) {
            $style->error('state.json is corrupt.');

            return Command::FAILURE;
        }

        $targetMajor = is_int($state['target'] ?? null) ? $state['target'] : 0;
        $completedStep = is_string($state['completed_step'] ?? null) ? $state['completed_step'] : '';

        if ($targetMajor === 0) {
            $style->error('state.json missing target major.');

            return Command::FAILURE;
        }

        $style->title(sprintf('Resuming Laravel %d upgrade', $targetMajor));
        $style->text(sprintf('Last completed step: %s', $completedStep !== '' ? $completedStep : '(start)'));

        // Determine what step to resume from.
        $resumeFrom = match ($completedStep) {
            '' => 'deps',
            'deps' => 'install',
            'install' => 'rector',
            'rector' => 'verify',
            default => null,
        };

        if ($resumeFrom === null) {
            $style->success('All steps already completed. Nothing to resume.');

            return Command::SUCCESS;
        }

        $style->text(sprintf('Resuming from: %s', $resumeFrom));

        // Delegate to the `to` command which handles each step atomically.
        $application = $this->getApplication();
        $toInput = new ArrayInput([
            'command' => 'to',
            'target-major' => (string) $targetMajor,
            '--working-dir' => $workingDirectory,
        ]);

        if ($application instanceof Application) {
            return $application->find('to')->run($toInput, $output);
        }

        return Command::FAILURE;
    }
}
