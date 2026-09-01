<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Report;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\ReportWriter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function test_report_json_preserves_low_confidence_advisory_findings(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $collector = new FindingCollector;
        $collector->add(
            'laravelUpgrade.doctrineRemovedMethods',
            'medium',
            11,
            'app/Database.php',
            7,
            'getDoctrineSomethingElse() was removed from Laravel 11 (low-confidence).',
            confidence: 'low',
        );
        $collector->writeJsonl($this->directory.'/.laravel-upgrade/findings.jsonl');

        (new UpgradeReportGenerator)->generate(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'run-low-confidence',
        ));

        $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
        self::assertIsString($contents);
        $report = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        $findings = $report['findings'] ?? null;
        self::assertIsArray($findings);
        $finding = $findings[0] ?? null;
        self::assertIsArray($finding);
        self::assertSame('low', $finding['confidence'] ?? null);
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

    public function test_finished_at_is_only_set_for_the_overall_final_transition(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $plan = new UpgradePlan(10, 13);

        foreach ([
            [10, 11, true],
            [11, 12, true],
            [12, 13, false],
        ] as [$from, $to, $expectNull]) {
            $generator->generate(new UpgradeContext(
                $this->directory,
                $plan,
                'run-1',
                activeFromMajor: $from,
                activeToMajor: $to,
            ));

            $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
            self::assertIsString($contents);
            $report = json_decode($contents, true);
            self::assertIsArray($report);
            self::assertArrayHasKey('finishedAt', $report);
            self::assertSame($expectNull, $report['finishedAt'] === null);
            self::assertIsString($report['updatedAt'] ?? null);
        }
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

    public function test_record_step_accumulates_transitions_and_renders_appendix_sections_after_failure(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $plan = new UpgradePlan(10, 13);
        $dependencyContext = new UpgradeContext(
            $this->directory,
            $plan,
            'run-1',
            activeFromMajor: 10,
            activeToMajor: 11,
        );

        $generator->recordStep($dependencyContext, new StepExecutionResult(
            '10->11',
            10,
            11,
            'dependencies',
            StepResult::successful(
                changedFiles: ['composer.json'],
                message: 'Dependency plan recorded.',
                data: [
                    'decisions' => [[
                        'package' => 'laravel/framework',
                        'section' => 'require',
                        'from' => '^10.0',
                        'to' => '^11.0',
                        'reason' => 'matrix',
                    ]],
                ],
            ),
        ));

        $generator->recordStep(new UpgradeContext(
            $this->directory,
            new UpgradePlan(11, 13),
            'run-1',
            activeFromMajor: 11,
            activeToMajor: 12,
        ), new StepExecutionResult(
            '11->12',
            11,
            12,
            'code',
            StepResult::successful(
                changedFiles: ['app/Example.php'],
                message: 'Code changes applied.',
                data: [
                    'appliedRuleCounts' => ['ExampleRule' => 2],
                ],
            ),
        ));

        $generator->recordStep(new UpgradeContext(
            $this->directory,
            $plan,
            'run-1',
            activeFromMajor: 11,
            activeToMajor: 12,
        ), new StepExecutionResult(
            '11->12',
            11,
            12,
            'verify',
            StepResult::failed(
                message: 'Tests failed.',
                findingsCount: 1,
                data: [
                    'findings' => [[
                        'id' => 'f-0001',
                        'ruleId' => 'laravelUpgrade.12.tests',
                        'severity' => 'high',
                        'laravelVersion' => 12,
                        'file' => 'tests/ExampleTest.php',
                        'line' => 18,
                        'message' => 'Review the failed test.',
                        'action' => 'Fix the test before continuing.',
                        'guideUrl' => 'https://laravel.com/docs/12.x/upgrade',
                    ]],
                    'checks' => ['tests' => false],
                ],
                exitCode: 1,
            ),
        ));

        $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
        self::assertIsString($contents);
        $report = json_decode($contents, true);
        self::assertIsArray($report);
        self::assertSame(1, $report['schemaVersion'] ?? null);
        self::assertSame('run-1', $report['runId'] ?? null);
        $steps = $report['steps'] ?? null;
        self::assertIsArray($steps);
        self::assertCount(3, $steps);
        $dependencies = $report['dependencies'] ?? null;
        self::assertIsArray($dependencies);
        self::assertCount(1, $dependencies);
        $dependency = $dependencies[0] ?? null;
        self::assertIsArray($dependency);
        self::assertSame('^10.0', $dependency['from'] ?? null);
        self::assertSame('^11.0', $dependency['to'] ?? null);
        self::assertNull($dependency['installed'] ?? null);
        self::assertArrayNotHasKey('current', $dependency);
        self::assertArrayNotHasKey('proposed', $dependency);
        $codeChanges = $report['codeChanges'] ?? null;
        self::assertIsArray($codeChanges);
        self::assertCount(1, $codeChanges);
        $failedStep = $steps[2] ?? null;
        self::assertIsArray($failedStep);
        self::assertSame('failed', $failedStep['status'] ?? null);
        $findings = $report['findings'] ?? null;
        self::assertIsArray($findings);
        $finding = $findings[0] ?? null;
        self::assertIsArray($finding);
        self::assertSame('laravelUpgrade.12.tests', $finding['ruleId'] ?? null);
        $verification = $report['verification'] ?? null;
        self::assertIsArray($verification);
        self::assertArrayHasKey('tests', $verification);
        $verificationHistory = $report['verificationHistory'] ?? null;
        self::assertIsArray($verificationHistory);
        self::assertCount(1, $verificationHistory);

        $markdown = file_get_contents($this->directory.'/UPGRADE-REPORT.md');
        self::assertIsString($markdown);
        $headings = [
            '## Summary',
            '## Manual actions',
            '## Dependencies',
            '## Code changes',
            '## Skeleton/config',
            '## Advisories',
            '## Verification',
            '## What the tool did not do',
        ];
        $positions = [];

        foreach ($headings as $heading) {
            $position = strpos($markdown, $heading);
            self::assertNotFalse($position);
            $positions[] = $position;
        }

        $sortedPositions = $positions;
        sort($sortedPositions);
        self::assertSame($sortedPositions, $positions);
        self::assertStringContainsString('Review the failed test.', $markdown);
    }

    public function test_report_identity_preserves_metadata_variants_and_collapses_exact_duplicates(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $context = new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'run-1',
            activeFromMajor: 10,
            activeToMajor: 11,
        );
        $finding = static fn (string $severity, string $action): array => [
            'ruleId' => 'rule.same',
            'severity' => $severity,
            'laravelVersion' => 11,
            'file' => 'app/Example.php',
            'line' => 4,
            'message' => 'Same finding.',
            'action' => $action,
            'guideUrl' => 'https://example.test',
            'autoFixed' => false,
            'confidence' => 'high',
        ];

        foreach ([
            ['advisories', [$finding('info', 'First guidance.')]],
            ['verify', [$finding('medium', 'Second guidance.')]],
            ['post', [$finding('info', 'First guidance.')]],
        ] as [$step, $findings]) {
            $generator->recordStep($context, new StepExecutionResult(
                '10->11',
                10,
                11,
                $step,
                StepResult::successful(
                    findingsCount: 1,
                    data: ['findings' => $findings],
                ),
            ));
        }

        $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
        self::assertIsString($contents);
        $report = json_decode($contents, true);
        self::assertIsArray($report);
        $findings = $report['findings'] ?? null;
        self::assertIsArray($findings);
        self::assertCount(2, $findings);
        self::assertSame(['info', 'medium'], array_column($findings, 'severity'));
    }

    public function test_regenerate_reads_canonical_json_and_writes_the_project_root_report(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;
        $generator->generate(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'run-1',
        ));
        file_put_contents($this->directory.'/UPGRADE-REPORT.md', 'stale');

        $result = $generator->regenerate($this->directory);

        self::assertSame((string) realpath($this->directory).'/UPGRADE-REPORT.md', $result['markdown']);
        self::assertStringContainsString('## Summary', (string) file_get_contents($this->directory.'/UPGRADE-REPORT.md'));
        self::assertFileDoesNotExist($this->directory.'/reports/UPGRADE-REPORT.md');
    }

    public function test_regenerate_rejects_missing_canonical_json(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        $generator = new UpgradeReportGenerator;

        $this->expectException(RuntimeException::class);
        $generator->regenerate($this->directory);
    }

    public function test_regenerate_rejects_malformed_canonical_json(): void
    {
        mkdir($this->directory.'/.laravel-upgrade', 0777, true);
        file_put_contents($this->directory.'/.laravel-upgrade/report.json', '{not-json');

        $this->expectException(RuntimeException::class);
        (new UpgradeReportGenerator)->regenerate($this->directory);
    }

    public function test_writer_reports_short_markdown_writes(): void
    {
        mkdir($this->directory, 0777, true);
        $this->expectException(RuntimeException::class);

        (new ReportWriter)->writeMarkdownReport([], $this->directory);
    }

    public function test_writer_reports_short_json_writes(): void
    {
        mkdir($this->directory, 0777, true);
        $this->expectException(RuntimeException::class);

        (new ReportWriter)->writeJson([], [], $this->directory);
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
