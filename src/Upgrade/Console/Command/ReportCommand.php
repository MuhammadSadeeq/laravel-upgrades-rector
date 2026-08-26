<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\ReportWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Regenerates UPGRADE-REPORT.md and report.json from collected findings
 * (plan P4-11).
 */
final class ReportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('report')
            ->setDescription('Generate UPGRADE-REPORT.md from upgrade findings')
            ->addOption('findings-jsonl', null, InputOption::VALUE_REQUIRED, 'Path to findings JSONL file')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Directory for generated reports', '.laravel-upgrade');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jsonlPath = $input->getOption('findings-jsonl');
        $outputDir = (string) $input->getOption('output-dir');

        if (! is_string($jsonlPath) || ! is_file($jsonlPath)) {
            // Try the default location.
            $default = '.laravel-upgrade/findings.jsonl';

            if (is_file($default)) {
                $jsonlPath = $default;
            } else {
                $output->writeln('<comment>No findings file found. Nothing to report.</>');

                return Command::SUCCESS;
            }
        }

        $collector = new FindingCollector;
        $lines = file($jsonlPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (is_array($lines)) {
            foreach ($lines as $line) {
                /** @var array<string, mixed>|null $data */
                $data = json_decode($line, true);

                if (is_array($data)) {
                    $collector->merge([Finding::fromArray($data)]);
                }
            }
        }

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $writer = new ReportWriter;
        $allFindings = $collector->all();
        $project = ['from' => '?', 'to' => '?', 'php' => PHP_VERSION];

        $writer->writeMarkdown($allFindings, $project, $outputDir.'/UPGRADE-REPORT.md');
        $writer->writeJson($allFindings, $project, $outputDir.'/report.json');

        $output->writeln(sprintf(
            'Report written: %s (%d findings)',
            $outputDir.'/UPGRADE-REPORT.md',
            count($allFindings)
        ));

        return Command::SUCCESS;
    }
}
