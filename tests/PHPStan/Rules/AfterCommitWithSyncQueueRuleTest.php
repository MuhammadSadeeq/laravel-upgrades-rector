<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\AfterCommitWithSyncQueueRule;

/** @extends Laravel11RuleTestCase<AfterCommitWithSyncQueueRule> */
final class AfterCommitWithSyncQueueRuleTest extends Laravel11RuleTestCase
{
    private string $queueDefault = 'sync';

    protected function getRule(): AfterCommitWithSyncQueueRule
    {
        return new AfterCommitWithSyncQueueRule($this->queueDefault);
    }

    public function test_after_commit_job_method_property_and_declaration_are_reported_for_sync_default(): void
    {
        $this->analyse([__DIR__.'/Fixture/sync-queue-job.php'], [
            [
                'Laravel 11 synchronous queue jobs now respect after-commit settings.',
                7,
                'Review transaction timing; use beforeCommit() or remove afterCommit when immediate execution is required.',
            ],
            [
                'Laravel 11 synchronous queue jobs now respect after-commit settings.',
                12,
                'Review transaction timing; use beforeCommit() or remove afterCommit when immediate execution is required.',
            ],
            [
                'Laravel 11 synchronous queue jobs now respect after-commit settings.',
                13,
                'Review transaction timing; use beforeCommit() or remove afterCommit when immediate execution is required.',
            ],
        ]);
    }

    public function test_queue_config_uses_the_actual_after_commit_option_shape(): void
    {
        $this->analyse([__DIR__.'/Fixture/config/queue.php'], []);
    }

    public function test_after_commit_job_is_safe_when_default_queue_is_not_sync(): void
    {
        $this->queueDefault = 'database';
        $this->analyse([__DIR__.'/Fixture/sync-queue-job.php'], []);
    }
}
