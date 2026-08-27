<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Orchestrator;

use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use PHPUnit\Framework\TestCase;

final class UpgradePlanTest extends TestCase
{
    public function test_multi_major_upgrade_is_split_into_strict_transitions(): void
    {
        $plan = new UpgradePlan(10, 13);

        self::assertSame([11, 12, 13], $plan->transitions());
        self::assertSame(['10->11', '11->12', '12->13'], $plan->transitionLabels());
        self::assertSame([
            'preflight',
            'dependencies',
            'install',
            'skeleton',
            'code',
            'advisories',
            'post',
            'verify',
            'commit',
        ], $plan->steps());
    }

    public function test_equal_current_and_target_is_an_explicit_no_op(): void
    {
        $plan = new UpgradePlan(12, 12, true);

        self::assertTrue($plan->isNoOp());
        self::assertTrue($plan->isPlanMode());
        self::assertSame([], $plan->transitions());
    }

    public function test_from_and_skip_steps_are_validated_and_applied(): void
    {
        $plan = new UpgradePlan(10, 11, false, 'code', 'skeleton, post, skeleton');

        self::assertSame(['skeleton', 'post'], $plan->skipSteps);
        self::assertSame(['code', 'advisories', 'verify', 'commit'], $plan->steps());
    }

    public function test_from_step_applies_only_to_the_first_transition(): void
    {
        $plan = new UpgradePlan(10, 13, false, 'code');

        self::assertSame(['code', 'advisories', 'post', 'verify', 'commit'], $plan->stepsForTransition(11));
        self::assertSame($plan->canonicalSteps(), $plan->stepsForTransition(12));
        self::assertSame($plan->canonicalSteps(), $plan->stepsForTransition(13));
    }

    public function test_unsupported_or_backward_targets_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UpgradePlan(10, 14);
    }

    public function test_backward_upgrade_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UpgradePlan(12, 11);
    }

    public function test_unknown_from_step_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UpgradePlan(10, 11, false, 'rector');
    }

    public function test_unknown_comma_skip_step_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UpgradePlan(10, 11, false, null, 'code,unknown');
    }

    public function test_verify_cannot_be_skipped(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UpgradePlan(10, 11, false, null, 'verify');
    }
}
