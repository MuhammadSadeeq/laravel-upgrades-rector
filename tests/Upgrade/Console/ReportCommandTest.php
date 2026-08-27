<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Console;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Console\Command\ReportCommand;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ReportCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-upgrade-report-command-'.bin2hex(random_bytes(5));
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        file_put_contents($this->directory.'/composer.json', '{"name":"example/app"}\n');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_reads_canonical_report_and_writes_markdown_at_project_root(): void
    {
        (new UpgradeReportGenerator)->generate(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'run-1',
        ));
        file_put_contents($this->directory.'/UPGRADE-REPORT.md', 'old');

        $outputDirectory = $this->directory.'/unrelated-output';
        $command = new CommandTester(new ReportCommand);
        $exit = $command->execute([
            '--working-dir' => $this->directory,
            '--output-dir' => $outputDirectory,
        ]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('## Summary', (string) file_get_contents($this->directory.'/UPGRADE-REPORT.md'));
        self::assertFileDoesNotExist($outputDirectory.'/UPGRADE-REPORT.md');
        self::assertStringContainsString($this->directory.'/UPGRADE-REPORT.md', $command->getDisplay());
    }

    public function test_missing_or_corrupt_canonical_report_is_a_failure(): void
    {
        $command = new CommandTester(new ReportCommand);
        self::assertSame(1, $command->execute(['--working-dir' => $this->directory]));

        file_put_contents($this->directory.'/.laravel-upgrade/report.json', '{not-json');
        self::assertSame(1, $command->execute(['--working-dir' => $this->directory]));
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($directory);
    }
}
