<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\WithScheduleTimingRule;

/** @extends Laravel13RuleTestCase<WithScheduleTimingRule> */
final class WithScheduleTimingRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): WithScheduleTimingRule
    {
        return new WithScheduleTimingRule;
    }

    public function test_with_schedule_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/schedule-with-schedule.php'], [[
            'ApplicationBuilder::withSchedule() registration is deferred in Laravel 13.',
            7,
            'Review bootstrap logic that relied on the schedule callback running immediately.',
        ]]);
    }

    public function test_with_schedule_is_reported_in_any_call_position(): void
    {
        $this->analyse([__DIR__.'/Fixture/schedule-all-positions.php'], [
            [
                'ApplicationBuilder::withSchedule() registration is deferred in Laravel 13.',
                7,
                'Review bootstrap logic that relied on the schedule callback running immediately.',
            ],
            [
                'ApplicationBuilder::withSchedule() registration is deferred in Laravel 13.',
                12,
                'Review bootstrap logic that relied on the schedule callback running immediately.',
            ],
        ]);
    }

    public function test_old_name_and_unrelated_receiver_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/schedule-safe.php'], []);
    }
}
