<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Git;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Git\GitCheckpointService;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradeRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRequest;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Process\ProcessRunner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepInterface;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GitCheckpointServiceTest extends TestCase
{
    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir().'/laravel-upgrade-git-'.bin2hex(random_bytes(8));
        mkdir($this->projectDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDirectory);
    }

    public function test_plan_and_no_git_modes_do_not_launch_git_or_write_state(): void
    {
        $runner = new GitCheckpointFakeRunner;
        $planContext = $this->context([], true);
        $service = new GitCheckpointService($runner);

        self::assertTrue($service->prepare($planContext)->isSkipped());
        self::assertTrue($service->afterStep('dependencies', $planContext, StepResult::successful(['composer.json']))->isSkipped());
        self::assertTrue($service->finalize($planContext)->isSkipped());
        self::assertSame([], $runner->requests);
        self::assertDirectoryDoesNotExist($this->projectDirectory.'/.laravel-upgrade');

        $noGitRunner = new GitCheckpointFakeRunner;
        $noGitContext = $this->context(['noGit' => true]);
        $noGit = new GitCheckpointService($noGitRunner);

        self::assertTrue($noGit->prepare($noGitContext)->isSkipped());
        self::assertTrue($noGit->finalize($noGitContext)->isSkipped());
        self::assertSame([], $noGitRunner->requests);
    }

    public function test_dirty_baseline_is_never_staged_even_when_a_new_path_has_the_same_checkpoint(): void
    {
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0, " M app/existing.php\0?? notes.txt\0"),
            $this->processResult(0, " M app/existing.php\0 M composer.json\0?? .laravel-upgrade/git-baseline.json\0"),
            $this->processResult(0),
            $this->processResult(0, 'committed'),
        ]);
        $service = new GitCheckpointService($runner);
        $result = $service->afterStep(
            'dependencies',
            $this->context(['allowDirty' => true]),
            StepResult::successful(['composer.json']),
        );

        self::assertTrue($result->isSuccessful(), $result->message);
        $add = $runner->requests[3] ?? null;
        self::assertInstanceOf(ProcessRequest::class, $add);
        self::assertSame('add', $add->arguments[1] ?? null);
        self::assertSame(['composer.json'], array_slice($add->arguments, 3));
        $commit = $runner->requests[4] ?? null;
        self::assertInstanceOf(ProcessRequest::class, $commit);
        self::assertStringContainsString('propose Laravel 11 dependencies', $commit->arguments[4] ?? '');
    }

    public function test_paths_with_spaces_are_passed_as_single_argv_entries_and_empty_checkpoints_are_skipped(): void
    {
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
            $this->processResult(0, "?? app/file with space.php\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $service = new GitCheckpointService($runner);
        $result = $service->afterStep(
            'skeleton',
            $this->context(),
            StepResult::successful([
                '../outside.php',
                'a/../../escape.php',
                '.laravel-upgrade/findings.jsonl',
                'app/file with space.php',
            ]),
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(['app/file with space.php'], array_slice($runner->requests[3]->arguments, 3));

        $emptyRunner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $empty = (new GitCheckpointService($emptyRunner))->afterStep(
            'install',
            $this->context(),
            StepResult::successful(['composer.lock']),
        );

        self::assertTrue($empty->isSkipped());
        self::assertCount(3, $emptyRunner->requests);
    }

    public function test_checkpoint_messages_and_post_includes_pending_code_and_new_migrations(): void
    {
        $cases = [
            ['dependencies', ['composer.json'], 'chore(upgrade): propose Laravel 11 dependencies', ['composer.json']],
            ['install', ['composer.lock'], 'chore(upgrade): install Laravel 11 dependencies', ['composer.lock']],
            ['skeleton', ['config/app.php'], 'chore(upgrade): sync Laravel 11 skeleton', ['config/app.php']],
        ];

        foreach ($cases as [$step, $changedFiles, $message, $expectedPaths]) {
            $runner = new GitCheckpointFakeRunner([
                $this->processResult(0, $this->projectDirectory),
                $this->processResult(0),
                $this->processResult(0, '?? '.($changedFiles[0])."\0"),
                $this->processResult(0),
                $this->processResult(0),
            ]);
            $result = (new GitCheckpointService($runner))->afterStep(
                $step,
                $this->context(),
                StepResult::successful($changedFiles),
            );

            self::assertTrue($result->isSuccessful(), $result->message);
            self::assertSame($message, $runner->requests[4]->arguments[4] ?? null);
            self::assertSame($expectedPaths, array_slice($runner->requests[3]->arguments, 3));
        }

        $postRunner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
            $this->processResult(0, "?? app/Changed.php\0?? database/migrations/2026_01_01_000000_create_new_table.php\0"),
            $this->processResult(0, "?? app/Changed.php\0?? database/migrations/2026_01_01_000000_create_new_table.php\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $postService = new GitCheckpointService($postRunner);
        $context = $this->context();
        $postService->afterStep('code', $context, StepResult::successful(['app/Changed.php']));
        $post = $postService->afterStep('post', $context, StepResult::successful());

        self::assertTrue($post->isSuccessful(), $post->message);
        self::assertSame(
            ['app/Changed.php', 'database/migrations/2026_01_01_000000_create_new_table.php'],
            array_slice($postRunner->requests[4]->arguments, 3),
        );
        self::assertSame('refactor(upgrade): apply Laravel 11 code changes', $postRunner->requests[5]->arguments[4] ?? null);
    }

    public function test_pending_code_paths_survive_a_new_service_instance_for_continue(): void
    {
        $codeRunner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
        ]);
        $context = $this->context();

        self::assertTrue((new GitCheckpointService($codeRunner))->afterStep(
            'code',
            $context,
            StepResult::successful(['app/Changed.php']),
        )->isSuccessful());

        $resumeRunner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0, "?? app/Changed.php\0?? .laravel-upgrade/git-baseline.json\0"),
            $this->processResult(0, "?? app/Changed.php\0?? .laravel-upgrade/git-baseline.json\0"),
            $this->processResult(0, "?? app/Changed.php\0?? .laravel-upgrade/git-baseline.json\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $resumed = (new GitCheckpointService($resumeRunner))->afterStep('post', $context, StepResult::successful());

        self::assertTrue($resumed->isSuccessful(), $resumed->message);
        self::assertSame(['app/Changed.php'], array_slice($resumeRunner->requests[4]->arguments, 3));
    }

    public function test_final_commit_preserves_existing_gitignore_and_never_stages_upgrade_metadata(): void
    {
        file_put_contents($this->projectDirectory.'/.gitignore', "vendor/\r\ncustom\r\n");
        file_put_contents($this->projectDirectory.'/UPGRADE-REPORT.md', "# Upgrade report\n");
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
            $this->processResult(0, "?? .gitignore\0?? UPGRADE-REPORT.md\0?? .laravel-upgrade/git-baseline.json\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $service = new GitCheckpointService($runner);
        $result = $service->finalize($this->context());

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame("vendor/\r\ncustom\r\n.laravel-upgrade/\r\n", file_get_contents($this->projectDirectory.'/.gitignore'));
        self::assertSame(['UPGRADE-REPORT.md', '.gitignore'], array_slice($runner->requests[3]->arguments, 3));
        self::assertStringContainsString('docs(upgrade): add Laravel 11 upgrade report', $runner->requests[4]->arguments[4] ?? '');
        self::assertFileDoesNotExist($this->projectDirectory.'/.laravel-upgrade/git-baseline.json');
    }

    public function test_nested_project_uses_repository_cwd_and_repo_relative_paths(): void
    {
        $repository = $this->projectDirectory.'/repository';
        $nestedProject = $repository.'/app with space';
        mkdir($nestedProject, 0777, true);
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $repository),
            $this->processResult(0),
            $this->processResult(0, "?? app with space/config.php\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $context = new UpgradeContext(
            $nestedProject,
            new UpgradePlan(10, 11),
            'nested-git-run',
        );
        $result = (new GitCheckpointService($runner))->afterStep(
            'skeleton',
            $context,
            StepResult::successful(['config.php']),
        );

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame(realpath($repository), $runner->requests[1]->workingDirectory ?? null);
        self::assertSame(realpath($repository), $runner->requests[2]->workingDirectory ?? null);
        self::assertSame(realpath($repository), $runner->requests[3]->workingDirectory ?? null);
        self::assertSame(['app with space/config.php'], array_slice($runner->requests[3]->arguments, 3));
        self::assertSame(realpath($repository), $runner->requests[4]->workingDirectory ?? null);
    }

    public function test_dirty_gitignore_is_left_untouched_while_a_new_report_can_be_committed(): void
    {
        $gitignore = "vendor/\n";
        file_put_contents($this->projectDirectory.'/.gitignore', $gitignore);
        file_put_contents($this->projectDirectory.'/UPGRADE-REPORT.md', "# Upgrade report\n");
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0, " M .gitignore\0"),
            $this->processResult(0, " M .gitignore\0?? UPGRADE-REPORT.md\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        $result = (new GitCheckpointService($runner))->finalize($this->context(['allowDirty' => true]));

        self::assertTrue($result->isSuccessful(), $result->message);
        self::assertSame($gitignore, file_get_contents($this->projectDirectory.'/.gitignore'));
        self::assertSame(['UPGRADE-REPORT.md'], array_slice($runner->requests[3]->arguments, 3));
        $gitignoreNote = $result->data['gitignore'] ?? null;
        self::assertIsString($gitignoreNote);
        self::assertStringContainsString('Pre-existing dirty .gitignore', $gitignoreNote);
        self::assertSame('--only', $runner->requests[4]->arguments[2] ?? null);
        self::assertSame(['UPGRADE-REPORT.md'], array_slice($runner->requests[4]->arguments, 6));
    }

    public function test_missing_report_skips_without_modifying_gitignore(): void
    {
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
        ]);
        $result = (new GitCheckpointService($runner))->finalize($this->context());

        self::assertTrue($result->isSkipped());
        self::assertSame('report-not-found', $result->data['reason'] ?? null);
        self::assertFileDoesNotExist($this->projectDirectory.'/.gitignore');
        self::assertFileExists($this->projectDirectory.'/.laravel-upgrade/git-baseline.json');
        self::assertCount(2, $runner->requests);
    }

    public function test_final_noop_boundary_clears_the_run_baseline(): void
    {
        file_put_contents($this->projectDirectory.'/.gitignore', "/.laravel-upgrade/\n");
        file_put_contents($this->projectDirectory.'/UPGRADE-REPORT.md', "# Upgrade report\n");
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0, "?? UPGRADE-REPORT.md\0"),
            $this->processResult(0, "?? UPGRADE-REPORT.md\0"),
        ]);
        $result = (new GitCheckpointService($runner))->finalize($this->context());

        self::assertTrue($result->isSkipped());
        self::assertSame('No new files require the docs(upgrade): add Laravel 11 upgrade report checkpoint.', $result->message);
        self::assertFileDoesNotExist($this->projectDirectory.'/.laravel-upgrade/git-baseline.json');
        self::assertCount(3, $runner->requests);
    }

    public function test_slash_prefixed_ignore_entry_is_recognized_without_rewriting_it(): void
    {
        $gitignore = "/.laravel-upgrade/\n";
        file_put_contents($this->projectDirectory.'/.gitignore', $gitignore);
        file_put_contents($this->projectDirectory.'/UPGRADE-REPORT.md', "# Upgrade report\n");
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
            $this->processResult(0, "?? UPGRADE-REPORT.md\0"),
            $this->processResult(0),
            $this->processResult(0),
        ]);
        self::assertTrue((new GitCheckpointService($runner))->finalize($this->context())->isSuccessful());
        self::assertSame($gitignore, file_get_contents($this->projectDirectory.'/.gitignore'));
    }

    public function test_non_repository_is_a_safe_skip(): void
    {
        $runner = new GitCheckpointFakeRunner([$this->processResult(128, '', 'not a repository')]);
        $service = new GitCheckpointService($runner);
        $result = $service->prepare($this->context());

        self::assertTrue($result->isSkipped());
        self::assertSame('git-unavailable', $result->data['reason'] ?? null);
        self::assertDirectoryDoesNotExist($this->projectDirectory.'/.laravel-upgrade');
    }

    public function test_add_failure_is_returned_for_runner_to_journal_and_resume(): void
    {
        $runner = new GitCheckpointFakeRunner([
            $this->processResult(0, $this->projectDirectory),
            $this->processResult(0),
            $this->processResult(0, "?? composer.json\0"),
            $this->processResult(7, '', 'permission denied'),
        ]);
        $store = new StateStore($this->projectDirectory);
        $steps = [];

        foreach (UpgradePlan::canonicalStepNames() as $name) {
            $steps[] = new GitRunnerStep($name);
        }

        $run = (new UpgradeRunner($store, $steps, null, new GitCheckpointService($runner)))
            ->run(new UpgradePlan(10, 11));

        self::assertTrue($run->isFailure());
        self::assertSame('dependencies', $run->failedStep);
        self::assertSame(1, $run->exitCode);
        $state = $store->load();
        self::assertIsArray($state);
        self::assertSame('failed', $state['status'] ?? null);
        self::assertSame('dependencies', $state['failedStep'] ?? null);
        self::assertSame('dependencies', $store->firstIncompleteStep(new UpgradePlan(10, 11)));
    }

    /** @param array<string, mixed> $options */
    private function context(array $options = [], bool $planMode = false): UpgradeContext
    {
        return new UpgradeContext(
            $this->projectDirectory,
            new UpgradePlan(10, 11, $planMode),
            'git-test-run',
            $options,
        );
    }

    private function processResult(int $exitCode, string $output = '', string $error = ''): ProcessResult
    {
        return new ProcessResult([], $exitCode, $output, $error);
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
final class GitCheckpointFakeRunner implements ProcessRunner
{
    /** @var list<ProcessRequest> */
    public array $requests = [];

    /** @param list<ProcessResult> $results */
    public function __construct(private array $results = []) {}

    public function run(ProcessRequest $request): ProcessResult
    {
        $this->requests[] = $request;

        if ($this->results === []) {
            throw new RuntimeException('Unexpected git process: '.$request->executable());
        }

        $result = array_shift($this->results);

        if (! $result instanceof ProcessResult) {
            throw new RuntimeException('Git process result queue was corrupted.');
        }

        return new ProcessResult($request->arguments, $result->exitCode, $result->output, $result->errorOutput);
    }
}

/** @internal */
final class GitRunnerStep implements StepInterface
{
    public function __construct(private readonly string $stepName) {}

    public function name(): string
    {
        return $this->stepName;
    }

    public function execute(UpgradeContext $context): StepResult
    {
        return $this->stepName === 'dependencies'
            ? StepResult::successful(['composer.json'])
            : StepResult::successful();
    }
}
