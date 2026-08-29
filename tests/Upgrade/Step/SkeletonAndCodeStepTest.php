<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonRepository;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\CodeStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\SkeletonSyncStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;

final class SkeletonAndCodeStepTest extends TestCase
{
    private string $projectDirectory;

    /** @var list<string> */
    private array $snapshotDirectories = [];

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir().'/laravel-upgrade-steps-'.bin2hex(random_bytes(8));
        mkdir($this->projectDirectory.'/app', 0777, true);
        mkdir($this->projectDirectory.'/vendor/bin', 0777, true);
        file_put_contents($this->projectDirectory.'/app/Example.php', "<?php\nreturn true;\n");
        file_put_contents($this->projectDirectory.'/vendor/bin/rector', 'fake rector');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDirectory);

        foreach ($this->snapshotDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function test_code_plan_uses_a_temporary_config_and_does_not_change_project_bytes(): void
    {
        $source = file_get_contents($this->projectDirectory.'/app/Example.php');
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 2, '{"totals":{"changed_files":1},"file_diffs":[{"file":"app/Example.php","applied_rectors":["ExampleRector"],"diff":"-old +new"}],"changed_files":["app/Example.php"]}'),
        ]);
        $result = $this->codeStep($runner)->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertSame(['app/Example.php'], $result->changedFiles);
        self::assertSame(['ExampleRector'], $result->data['appliedRules']);
        self::assertSame(['ExampleRector' => 1], $result->data['appliedRuleCounts']);
        $process = $result->data['process'] ?? null;
        self::assertIsArray($process);
        self::assertSame(2, $process['exitCode'] ?? null);
        self::assertSame(0, $result->findingsCount);
        self::assertSame($source, file_get_contents($this->projectDirectory.'/app/Example.php'));
        self::assertDirectoryDoesNotExist($this->projectDirectory.'/.laravel-upgrade');
        self::assertCount(1, $runner->requests);
        self::assertContains('--dry-run', $runner->requests[0]->arguments);
        self::assertContains('--output-format=json', $runner->requests[0]->arguments);
        self::assertContains('--clear-cache', $runner->requests[0]->arguments);
        self::assertTrue(str_starts_with($runner->requests[0]->arguments[3], sys_get_temp_dir()));
    }

    public function test_code_can_leave_rector_cache_intact_when_requested(): void
    {
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 0, '{"totals":{"changed_files":0},"file_diffs":[]}'),
        ]);

        $result = $this->codeStep($runner)->execute($this->context([
            'clearCache' => false,
        ], planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertNotContains('--clear-cache', $runner->requests[0]->arguments);
    }

    public function test_code_passes_project_composer_autoload_to_rector(): void
    {
        file_put_contents($this->projectDirectory.'/vendor/autoload.php', "<?php\n");
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 0, '{"totals":{"changed_files":0},"file_diffs":[]}'),
        ]);

        $result = $this->codeStep($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful());
        $arguments = $runner->requests[0]->arguments;
        $autoloadIndex = array_search('--autoload-file', $arguments, true);

        self::assertIsInt($autoloadIndex);
        self::assertSame($this->projectDirectory.'/vendor/autoload.php', $arguments[$autoloadIndex + 1] ?? null);
    }

    public function test_code_does_not_pass_missing_project_composer_autoload_to_rector(): void
    {
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 0, '{"totals":{"changed_files":0},"file_diffs":[]}'),
        ]);

        $result = $this->codeStep($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful());
        self::assertNotContains('--autoload-file', $runner->requests[0]->arguments);
    }

    public function test_code_reports_missing_binary_and_invalid_json(): void
    {
        $this->removeDirectory($this->projectDirectory.'/vendor');
        $missing = $this->codeStep(new StepFakeProcessRunner)->execute($this->context());

        self::assertTrue($missing->isFailed());
        self::assertSame('rector-binary', $missing->data['check']);

        mkdir($this->projectDirectory.'/vendor/bin', 0777, true);
        file_put_contents($this->projectDirectory.'/vendor/bin/rector', 'fake rector');
        $invalid = $this->codeStep(new StepFakeProcessRunner([
            new ProcessResult([], 9, 'not JSON', 'Rector crashed'),
        ]))->execute($this->context());

        self::assertTrue($invalid->isFailed());
        self::assertSame(9, $invalid->exitCode);
        self::assertStringContainsString('valid JSON', $invalid->message);
    }

    public function test_code_applies_changes_and_formats_only_changed_php_files_when_pint_exists(): void
    {
        file_put_contents($this->projectDirectory.'/vendor/bin/pint', 'fake pint');
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 0, '{"file_diffs":[{"file":"app/Example.php","applied_rectors":["ExampleRector"]},{"file":"README.md","applied_rectors":[]}],"changed_files":["app/Example.php","README.md"]}'),
            new ProcessResult([], 0, 'formatted'),
        ]);
        $result = $this->codeStep($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful());
        self::assertFileExists($this->projectDirectory.'/.laravel-upgrade/rector-11.php');
        self::assertCount(2, $runner->requests);
        self::assertSame('pint', basename($runner->requests[1]->arguments[0]));
        self::assertSame(['app/Example.php'], array_slice($runner->requests[1]->arguments, 1));
        self::assertSame(0, $result->findingsCount);
        self::assertSame(['ExampleRector' => 1], $result->data['appliedRuleCounts']);
    }

    public function test_code_rejects_dot_segments_in_changed_file_paths(): void
    {
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 0, '{"file_diffs":[{"file":"./app/Example.php"},{"file":"a/../../outside.php"}],"changed_files":["./../outside.php","app/Example.php"]}'),
        ]);

        $result = $this->codeStep($runner)->execute($this->context());

        self::assertTrue($result->isSuccessful());
        self::assertSame(['app/Example.php'], $result->changedFiles);
    }

    public function test_code_rejects_non_project_configured_rector_binaries(): void
    {
        $outside = $this->codeStep(new StepFakeProcessRunner)->execute($this->context([
            'rectorBinary' => '/bin/sh',
        ]));
        $traversal = $this->codeStep(new StepFakeProcessRunner)->execute($this->context([
            'rectorBinary' => '../vendor/bin/rector',
        ]));

        self::assertTrue($outside->isFailed());
        self::assertSame('rector-binary', $outside->data['check']);
        self::assertTrue($traversal->isFailed());
        self::assertSame('rector-binary', $traversal->data['check']);
    }

    public function test_code_never_falls_back_to_a_project_pint_for_invalid_configuration(): void
    {
        file_put_contents($this->projectDirectory.'/vendor/bin/pint', 'fake pint');
        $runner = new StepFakeProcessRunner([
            new ProcessResult([], 0, '{"changed_files":["app/Example.php"]}'),
        ]);

        $result = $this->codeStep($runner)->execute($this->context([
            'pintBinary' => '../vendor/bin/pint',
        ]));

        self::assertTrue($result->isSuccessful());
        self::assertCount(1, $runner->requests);
        $pint = $result->data['pint'] ?? null;
        self::assertIsArray($pint);
        self::assertSame('not-installed', $pint['reason'] ?? null);
    }

    public function test_code_skips_pint_when_disabled_and_surfaces_pint_failure(): void
    {
        file_put_contents($this->projectDirectory.'/vendor/bin/pint', 'fake pint');
        $rectorOutput = '{"files":{"app/Example.php":{"applied_rectors":["ExampleRector"]}}}';
        $disabledRunner = new StepFakeProcessRunner([new ProcessResult([], 0, $rectorOutput)]);
        $disabled = $this->codeStep($disabledRunner)->execute($this->context(['noPint' => true]));

        self::assertTrue($disabled->isSuccessful());
        $pint = $disabled->data['pint'] ?? null;
        self::assertIsArray($pint);
        self::assertSame('disabled', $pint['reason'] ?? null);
        self::assertCount(1, $disabledRunner->requests);

        $failedRunner = new StepFakeProcessRunner([
            new ProcessResult([], 0, $rectorOutput),
            new ProcessResult([], 5, '', 'format failed'),
        ]);
        $failed = $this->codeStep($failedRunner)->execute($this->context());

        self::assertTrue($failed->isFailed());
        self::assertSame(5, $failed->exitCode);
    }

    public function test_skeleton_plan_with_partial_snapshots_is_neutral_and_reports_skip(): void
    {
        $root = $this->createSnapshotRoot(false);
        file_put_contents($root.'/10/old.txt', 'old');
        file_put_contents($root.'/11/new.txt', 'new');
        file_put_contents($this->projectDirectory.'/old.txt', 'project old');
        $before = file_get_contents($this->projectDirectory.'/old.txt');
        $step = new SkeletonSyncStep(new SkeletonStep(new SkeletonRepository($root)));

        $result = $step->execute($this->context(planMode: true));

        self::assertTrue($result->isSuccessful());
        self::assertSame($before, file_get_contents($this->projectDirectory.'/old.txt'));
        self::assertFileDoesNotExist($this->projectDirectory.'/new.txt');
        $sync = $result->data['sync'] ?? null;
        self::assertIsArray($sync);
        self::assertSame([], $sync['added'] ?? null);
        self::assertSame([], $sync['removed'] ?? null);
        self::assertSame([], $sync['modified'] ?? null);
        self::assertSame([], $sync['renamed'] ?? null);
        self::assertSame(1, $result->findingsCount);
    }

    public function test_skeleton_conflicts_fail_without_overwriting_ours(): void
    {
        $root = $this->createSnapshotRoot(true);
        file_put_contents($root.'/10/config.php', "base\n");
        file_put_contents($root.'/11/config.php', "theirs\n");
        file_put_contents($this->projectDirectory.'/config.php', "ours\n");

        $result = (new SkeletonSyncStep(new SkeletonStep(new SkeletonRepository($root))))
            ->execute($this->context());

        self::assertTrue($result->isFailed());
        self::assertSame(4, $result->exitCode);
        self::assertSame("ours\n", file_get_contents($this->projectDirectory.'/config.php'));
        self::assertFileExists($this->projectDirectory.'/.laravel-upgrade/merge/config.php.merged');
        $sync = $result->data['sync'] ?? null;
        self::assertIsArray($sync);
        $conflicts = $sync['conflicts'] ?? null;
        self::assertIsArray($conflicts);
        self::assertContains('config.php', $conflicts);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function context(array $options = [], bool $planMode = false): UpgradeContext
    {
        return new UpgradeContext(
            $this->projectDirectory,
            new UpgradePlan(10, 11, $planMode),
            'test-run',
            $options,
        );
    }

    private function codeStep(StepFakeProcessRunner $runner): CodeStep
    {
        return new CodeStep($runner);
    }

    private function createSnapshotRoot(bool $complete): string
    {
        $root = sys_get_temp_dir().'/laravel-upgrade-snapshots-'.bin2hex(random_bytes(8));
        mkdir($root.'/10', 0777, true);
        mkdir($root.'/11', 0777, true);
        file_put_contents($root.'/MANIFEST.json', json_encode([
            '10' => ['complete' => $complete],
            '11' => ['complete' => $complete],
        ], JSON_THROW_ON_ERROR));

        $this->snapshotDirectories[] = $root;

        return $root;
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
final class StepFakeProcessRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    /** @var list<ProcessResult> */
    private array $results;

    /**
     * @param  list<ProcessResult>  $results
     */
    public function __construct(array $results = [])
    {
        $this->results = $results;
    }

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;
        $result = array_shift($this->results);

        if (! $result instanceof ProcessResult) {
            throw new \RuntimeException('Unexpected process request.');
        }

        return new ProcessResult($request->arguments, $result->exitCode, $result->output, $result->errorOutput);
    }
}
