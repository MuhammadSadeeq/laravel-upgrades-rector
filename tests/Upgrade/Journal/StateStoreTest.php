<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Journal;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateConflictException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Journal\StateStore;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StateStoreTest extends TestCase
{
    private string $workingDirectory;

    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir().'/laravel-upgrade-state-'.bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workingDirectory);
    }

    public function test_journal_resumes_first_incomplete_step_and_tracks_transition(): void
    {
        $plan = new UpgradePlan(10, 11);
        $store = new StateStore($this->workingDirectory);
        $started = $store->start($plan, 'run-1');

        self::assertSame('run-1', $started['runId']);
        self::assertSame('10->11', $started['currentTransition']);
        self::assertSame('preflight', $store->firstIncompleteStep($plan));

        $store->recordCompletedStep($plan, 'preflight');
        self::assertSame('dependencies', $store->firstIncompleteStep($plan));

        $store->recordFailedStep($plan, 'dependencies', 'solver failed');
        $failed = $store->load();
        self::assertIsArray($failed);
        self::assertSame('failed', $failed['status']);
        self::assertSame('dependencies', $failed['failedStep']);
        self::assertSame('dependencies', $store->firstIncompleteStep($plan));
    }

    public function test_active_journal_refuses_a_different_target(): void
    {
        $store = new StateStore($this->workingDirectory);
        $store->start(new UpgradePlan(10, 11), 'run-1');

        $this->expectException(StateConflictException::class);
        $store->start(new UpgradePlan(10, 12), 'run-2');
    }

    public function test_completed_journal_is_cleared_only_after_every_step_finishes(): void
    {
        $plan = new UpgradePlan(10, 11);
        $store = new StateStore($this->workingDirectory);
        $store->start($plan, 'run-1');
        $store->recordCompletedStep($plan, 'preflight');

        self::assertFileExists($store->path());
        self::assertSame('dependencies', $store->firstIncompleteStep($plan));

        foreach (['dependencies', 'install', 'skeleton', 'code', 'advisories', 'post', 'verify', 'commit'] as $step) {
            $result = $store->recordCompletedStep($plan, $step);
        }

        self::assertSame('completed', $result['status']);
        self::assertNull($store->load());
        self::assertFileDoesNotExist($store->path());
    }

    public function test_skipping_commit_advances_a_single_transition_after_verify(): void
    {
        $plan = new UpgradePlan(10, 11, false, null, 'commit');
        $store = new StateStore($this->workingDirectory);
        $store->start($plan, 'run-1');

        $steps = $plan->stepsForTransition(11);
        $result = $store->recordCompletedStep($plan, $steps[0]);

        foreach (array_slice($steps, 1) as $step) {
            $result = $store->recordCompletedStep($plan, $step);
        }

        self::assertSame('completed', $result['status']);
        self::assertNull($store->load());
    }

    public function test_skipping_commit_advances_each_multi_major_transition(): void
    {
        $plan = new UpgradePlan(10, 12, false, null, 'commit');
        $store = new StateStore($this->workingDirectory);
        $store->start($plan, 'run-1');

        $steps = $plan->stepsForTransition(11);
        $state = $store->recordCompletedStep($plan, $steps[0]);

        foreach (array_slice($steps, 1) as $step) {
            $state = $store->recordCompletedStep($plan, $step);
        }

        self::assertSame('11->12', $state['currentTransition']);
        self::assertSame(11, $state['currentMajor']);

        $steps = $plan->stepsForTransition(12);
        $state = $store->recordCompletedStep($plan, $steps[0]);

        foreach (array_slice($steps, 1) as $step) {
            $state = $store->recordCompletedStep($plan, $step);
        }

        self::assertSame('completed', $state['status']);
        self::assertNull($store->load());
    }

    public function test_from_step_only_changes_the_first_transition_resume_point(): void
    {
        $plan = new UpgradePlan(10, 12, false, 'code');
        $store = new StateStore($this->workingDirectory);
        $store->start($plan, 'run-1');

        self::assertSame('code', $store->firstIncompleteStep($plan));

        $steps = $plan->stepsForTransition(11);

        foreach ($steps as $step) {
            $store->recordCompletedStep($plan, $step);
        }

        self::assertSame('preflight', $store->firstIncompleteStep($plan));
        self::assertSame($plan->canonicalSteps(), $plan->stepsForTransition(12));
    }

    public function test_corrupt_state_is_not_accepted(): void
    {
        mkdir(dirname((new StateStore($this->workingDirectory))->path()), 0777, true);
        file_put_contents((new StateStore($this->workingDirectory))->path(), '{not-json');

        self::assertNull((new StateStore($this->workingDirectory))->load());

        $this->expectException(RuntimeException::class);
        (new StateStore($this->workingDirectory))->start(new UpgradePlan(10, 11));
    }

    public function test_state_is_valid_json_with_no_leftover_atomic_temporary_file(): void
    {
        $store = new StateStore($this->workingDirectory);
        $store->start(new UpgradePlan(10, 11), 'run-1');

        $json = file_get_contents($store->path());
        self::assertIsString($json);
        self::assertIsArray(json_decode($json, true));
        self::assertSame([], glob($store->directory().'/state-*') ?: []);
    }

    public function test_plan_mode_never_creates_a_state_directory_or_file(): void
    {
        $store = new StateStore($this->workingDirectory, true);
        $plan = new UpgradePlan(10, 11, true);

        $store->start($plan, 'plan-run');
        $store->recordCompletedStep($plan, 'preflight');
        $store->recordFailedStep($plan, 'dependencies', 'preview');

        self::assertDirectoryDoesNotExist($store->directory());
        self::assertFileDoesNotExist($store->path());
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
