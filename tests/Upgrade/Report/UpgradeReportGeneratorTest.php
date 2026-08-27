<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Report;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;

final class UpgradeReportGeneratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-upgrade-report-'.bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_generates_root_markdown_and_internal_json_from_accumulated_findings(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $collector = new FindingCollector;
        $collector->add('laravelUpgrade.11.example', 'medium', 11, 'app/Test.php', 12, 'Review this change.');
        $collector->writeJsonl($this->directory.'/.laravel-upgrade/findings.jsonl');

        $result = (new UpgradeReportGenerator)->generate(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'run-1',
        ));

        self::assertSame('generated', $result['status']);
        self::assertFileExists($this->directory.'/UPGRADE-REPORT.md');
        self::assertFileExists($this->directory.'/.laravel-upgrade/report.json');
        self::assertStringContainsString('Review this change.', (string) file_get_contents($this->directory.'/UPGRADE-REPORT.md'));
        self::assertStringContainsString('laravelUpgrade.11.example', (string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'));
        self::assertSame([], glob($this->directory.'/.laravel-upgrade/.upgrade-report-*') ?: []);
    }

    public function test_report_metadata_uses_the_overall_plan_during_a_later_transition(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $context = new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 13),
            'run-1',
            activeFromMajor: 11,
            activeToMajor: 12,
        );

        $result = (new UpgradeReportGenerator)->generate($context);

        self::assertSame('generated', $result['status']);
        $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
        self::assertIsString($contents);
        $report = json_decode($contents, true);
        self::assertIsArray($report);
        $project = $report['project'] ?? null;
        self::assertIsArray($project);
        self::assertSame('10', $project['from'] ?? null);
        self::assertSame('13', $project['to'] ?? null);
    }

    public function test_plan_mode_is_a_no_write_report_preview(): void
    {
        $result = (new UpgradeReportGenerator)->generate(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11, true),
            'plan-1',
        ));

        self::assertSame(['status' => 'skipped', 'reason' => 'plan-mode'], $result);
        self::assertDirectoryDoesNotExist($this->directory);
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
