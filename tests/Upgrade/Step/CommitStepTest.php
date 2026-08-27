<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\BinaryResolver;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\SymfonyProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\UpgradeReportGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\CommitStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommitStepTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-upgrade-commit-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_final_report_is_the_committed_report_and_success_leaves_a_clean_tree(): void
    {
        $process = new SymfonyProcessRunner;
        self::assertSame(0, $process->run(new ProcessRequest(['git', 'init'], $this->directory))->exitCode);
        self::assertSame(0, $process->run(new ProcessRequest(['git', 'config', 'user.email', 'test@example.com'], $this->directory))->exitCode);
        self::assertSame(0, $process->run(new ProcessRequest(['git', 'config', 'user.name', 'Upgrade Test'], $this->directory))->exitCode);
        file_put_contents($this->directory.'/composer.json', "{\"name\":\"example/app\"}\n");
        self::assertSame(0, $process->run(new ProcessRequest(['git', 'add', 'composer.json'], $this->directory))->exitCode);
        self::assertSame(0, $process->run(new ProcessRequest(['git', 'commit', '-m', 'base'], $this->directory))->exitCode);

        $report = new UpgradeReportGenerator;
        $git = new GitCheckpointService($process, new BinaryResolver);
        $steps = [];

        foreach (UpgradePlan::canonicalStepNames() as $name) {
            $steps[] = $name === 'commit'
                ? new CommitStep($git, $report)
                : new CommitFixtureStep($name, $name === 'code' ? $this->directory : null);
        }

        $result = (new UpgradeRunner(
            new StateStore($this->directory),
            $steps,
            null,
            $git,
            $report,
        ))->run(new UpgradePlan(10, 11));

        self::assertTrue($result->success, $result->failureMessage ?? 'upgrade failed');
        $workingReport = file_get_contents($this->directory.'/UPGRADE-REPORT.md');
        self::assertIsString($workingReport);
        self::assertStringContainsString('- Last step: commit', $workingReport);
        $canonical = json_decode((string) file_get_contents($this->directory.'/.laravel-upgrade/report.json'), true);
        self::assertIsArray($canonical);
        $reportSteps = $canonical['steps'] ?? null;
        self::assertIsArray($reportSteps);
        $lastStep = $reportSteps[array_key_last($reportSteps)] ?? null;
        self::assertIsArray($lastStep);
        self::assertSame('commit', $lastStep['name'] ?? null);
        $checkpoint = null;

        foreach ($reportSteps as $reportStep) {
            if (is_array($reportStep) && ($reportStep['name'] ?? null) === 'post') {
                $checkpoint = $reportStep;
                break;
            }
        }

        self::assertIsArray($checkpoint);
        self::assertIsString($checkpoint['commit'] ?? null);
        self::assertMatchesRegularExpression('/^[0-9a-f]{7,64}$/i', (string) $checkpoint['commit']);
        $project = $canonical['project'] ?? null;
        self::assertIsArray($project);
        self::assertSame(2, $project['commits'] ?? null);
        $committedReport = $process->run(new ProcessRequest(['git', 'show', 'HEAD:UPGRADE-REPORT.md'], $this->directory));
        self::assertTrue($committedReport->isSuccessful(), $committedReport->combinedOutput());
        self::assertSame($workingReport, $committedReport->output);
        self::assertSame('', $process->run(new ProcessRequest(['git', 'status', '--porcelain'], $this->directory))->combinedOutput());
    }

    public function test_failed_finalization_clears_finished_at(): void
    {
        $runner = new CommitFailureProcessRunner([
            new ProcessResult([], 0, $this->directory),
            new ProcessResult([], 0),
            new ProcessResult([], 0, "?? UPGRADE-REPORT.md\0?? .gitignore\0"),
            new ProcessResult([], 7, '', 'commit denied'),
        ]);
        $report = new UpgradeReportGenerator;
        $step = new CommitStep(new GitCheckpointService($runner, new BinaryResolver), $report);
        $result = $step->execute(new UpgradeContext(
            $this->directory,
            new UpgradePlan(10, 11),
            'failed-final-commit',
        ));

        self::assertTrue($result->isFailed(), $result->message);
        self::assertFileExists($this->directory.'/.laravel-upgrade/report.json', $result->message);
        $contents = file_get_contents($this->directory.'/.laravel-upgrade/report.json');
        self::assertIsString($contents);
        $decoded = json_decode($contents, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('finishedAt', $decoded);
        self::assertNull($decoded['finishedAt']);
        $steps = $decoded['steps'] ?? null;
        self::assertIsArray($steps);
        $final = $steps[0] ?? null;
        self::assertIsArray($final);
        self::assertSame('failed', $final['status'] ?? null);
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
            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}

/** @internal */
final class CommitFixtureStep implements StepInterface
{
    public function __construct(private readonly string $stepName, private readonly ?string $directory = null) {}

    public function name(): string
    {
        return $this->stepName;
    }

    public function execute(UpgradeContext $context): StepResult
    {
        if ($this->stepName === 'code' && $this->directory !== null) {
            mkdir($this->directory.'/app', 0777, true);
            file_put_contents($this->directory.'/app/Changed.php', "<?php\n\nnamespace App;\n\nfinal class Changed {}\n");

            return StepResult::successful(['app/Changed.php'], message: 'Code fixture changed.');
        }

        return StepResult::successful(message: $this->stepName.' fixture completed.');
    }
}

/** @internal */
final class CommitFailureProcessRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    /** @param list<ProcessResult> $results */
    public function __construct(private array $results) {}

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;
        $result = array_shift($this->results);

        if (! $result instanceof ProcessResult) {
            throw new RuntimeException('Unexpected process request: '.$request->executable());
        }

        return new ProcessResult($request->arguments, $result->exitCode, $result->output, $result->errorOutput);
    }
}
