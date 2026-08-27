<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Regenerates the root Markdown report from canonical report.json (P4-11). */
final class ReportCommand extends Command
{
    public function __construct(private readonly UpgradeReportGenerator $reportGenerator = new UpgradeReportGenerator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('report')
            ->setDescription('Regenerate UPGRADE-REPORT.md from canonical report.json')
            ->addOption('working-dir', 'd', InputOption::VALUE_REQUIRED, 'Project directory', '.')
            // Kept as a parse-compatible option for callers of the pre-P4-11
            // command. Reports are always read from the project state folder.
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Deprecated; reports are written at the project root')
            ->addOption('findings-jsonl', null, InputOption::VALUE_REQUIRED, 'Deprecated; report.json is the canonical input');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDirectory = $this->workingDirectory($input);

        if ($workingDirectory === null) {
            $output->writeln('<error>The working directory does not exist.</error>');

            return Command::FAILURE;
        }

        try {
            $result = $this->reportGenerator->regenerate($workingDirectory);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Could not regenerate report: '.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $markdown = $result['markdown'] ?? null;
        $markdown = is_string($markdown) ? $markdown : $workingDirectory.'/UPGRADE-REPORT.md';
        $findings = $result['findings'] ?? 0;
        $findings = is_int($findings) ? $findings : 0;

        $output->writeln(sprintf(
            'Report written: %s (%d findings)',
            $markdown,
            $findings,
        ));

        return Command::SUCCESS;
    }

    private function workingDirectory(InputInterface $input): ?string
    {
        $value = $input->getOption('working-dir');
        $directory = is_string($value) && $value !== '' ? $value : '.';
        $resolved = realpath($directory);

        return $resolved !== false && is_dir($resolved) ? $resolved : null;
    }
}
