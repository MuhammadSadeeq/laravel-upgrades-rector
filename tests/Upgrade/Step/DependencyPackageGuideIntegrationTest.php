<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrix;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ComposerProcessAdapter;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ConstraintPlanner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideAnalyzer;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideCatalog;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\StepExecutionResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\DependencyStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;

final class DependencyPackageGuideIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/dependency-guides-'.bin2hex(random_bytes(5));
        mkdir($this->directory, 0777, true);
        file_put_contents($this->directory.'/composer.json', json_encode([
            'require' => [
                'laravel/framework' => '^10.0',
                'livewire/livewire' => '^2.0',
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->directory.'/composer.lock', json_encode([
            'packages' => [
                ['name' => 'laravel/framework', 'version' => 'v10.48.0'],
                ['name' => 'livewire/livewire', 'version' => 'v2.12.0'],
            ],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR));
        mkdir($this->directory.'/app/Livewire', 0777, true);
        mkdir($this->directory.'/resources/views/livewire', 0777, true);
        file_put_contents($this->directory.'/app/Livewire/One.php', '<?php');
        file_put_contents($this->directory.'/app/Livewire/Two.php', '<?php');
        file_put_contents($this->directory.'/resources/views/livewire/three.blade.php', '<div />');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_apply_dependency_result_carries_crossing_findings_into_the_report_data(): void
    {
        $result = $this->step(new AlwaysSuccessfulRunner)->execute($this->context());

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertGreaterThan(0, $result->findingsCount);
        $findings = $result->data['findings'] ?? null;
        self::assertIsArray($findings);
        self::assertTrue($this->containsRule($findings, 'laravelUpgrade.packageGuide.livewire.livewire.3.component-upgrade'));
        $guides = $result->data['packageGuides'] ?? null;
        self::assertIsArray($guides);
        $guide = $guides[0] ?? null;
        self::assertIsArray($guide);
        self::assertSame(3, $guide['componentCount'] ?? null);
    }

    public function test_plan_dependency_result_is_dry_run_neutral_but_keeps_crossing_guidance(): void
    {
        $before = file_get_contents($this->directory.'/composer.json');
        $result = $this->step(new AlwaysSuccessfulRunner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame($before, file_get_contents($this->directory.'/composer.json'));
        self::assertNotEmpty($result->data['findings'] ?? []);
        self::assertNotEmpty($result->data['packageGuides'] ?? []);
    }

    public function test_dependency_findings_are_consumed_by_the_canonical_upgrade_report(): void
    {
        $result = $this->step(new AlwaysSuccessfulRunner)->execute($this->context());
        $report = new UpgradeReportGenerator;
        $report->recordStep(
            $this->context(),
            new StepExecutionResult('10->11', 10, 11, 'dependencies', $result),
        );

        $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $findings = $decoded['findings'] ?? null;
        self::assertIsArray($findings);
        self::assertTrue($this->containsRule($findings, 'laravelUpgrade.packageGuide.livewire.livewire.3.component-upgrade'));
        $steps = $decoded['steps'] ?? null;
        self::assertIsArray($steps);
        $step = $steps[0] ?? null;
        self::assertIsArray($step);
        $data = $step['data'] ?? null;
        self::assertIsArray($data);
        $guides = $data['packageGuides'] ?? null;
        self::assertIsArray($guides);
        $guide = $guides[0] ?? null;
        self::assertIsArray($guide);
        self::assertSame(3, $guide['componentCount'] ?? null);
    }

    private function step(ProcessRunner $runner): DependencyStep
    {
        return new DependencyStep(
            new ConstraintPlanner(
                new CompatibilityMatrix(dirname(__DIR__, 3).'/resources/compat/packages.json'),
                dirname(__DIR__, 3).'/resources/compat/removals.json',
            ),
            new ComposerProcessAdapter($runner),
            packageGuideAnalyzer: new PackageGuideAnalyzer(new PackageGuideCatalog(
                dirname(__DIR__, 3).'/resources/compat/package-guides.json',
            )),
        );
    }

    private function context(bool $planMode = false): UpgradeContext
    {
        return new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11, $planMode),
            'dependency-guides-test',
        );
    }

    /** @param array<array-key, mixed> $findings */
    private function containsRule(array $findings, string $ruleId): bool
    {
        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            if (($finding['ruleId'] ?? null) === $ruleId) {
                return true;
            }
        }

        return false;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}

/** @internal */
final class AlwaysSuccessfulRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;

        return new ProcessResult($request->arguments, 0, 'ok');
    }
}
