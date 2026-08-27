<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Step;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\StepResult;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Step\UpgradeContext;
use PHPUnit\Framework\TestCase;

final class StepValueObjectsTest extends TestCase
{
    public function test_context_exposes_plan_directory_run_and_options_without_writes(): void
    {
        $plan = new UpgradePlan(10, 11, true);
        $context = new UpgradeContext('/tmp/project', $plan, 'run-1', ['noInstall' => true]);

        self::assertTrue($context->isPlanMode());
        self::assertSame(10, $context->currentMajor());
        self::assertSame(11, $context->targetMajor());
        self::assertTrue($context->option('noInstall'));
        self::assertSame('fallback', $context->option('missing', 'fallback'));
    }

    public function test_step_results_have_explicit_outcomes(): void
    {
        $success = StepResult::successful(['config/app.php'], 2, 'done', ['major' => 11]);
        $failed = StepResult::failed('composer failed');
        $skipped = StepResult::skipped('plan mode');

        self::assertTrue($success->isSuccessful());
        self::assertSame(['config/app.php'], $success->changedFiles);
        self::assertSame(2, $success->findingsCount);
        self::assertSame(11, $success->data['major']);
        self::assertTrue($failed->isFailed());
        self::assertTrue($skipped->isSkipped());
    }

    public function test_invalid_result_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StepResult('unknown');
    }
}
