<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\CarbonIntervalFloatSupportRule;

/** @extends Laravel11RuleTestCase<CarbonIntervalFloatSupportRule> */
final class CarbonIntervalFloatSupportRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): CarbonIntervalFloatSupportRule
    {
        return new CarbonIntervalFloatSupportRule;
    }

    public function test_from_string_interval_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/carbon-interval-from-string.php'], [[
            'CarbonInterval now supports float values in Carbon 3.',
            9,
            'Verify that fractional intervals behave as expected.',
        ]]);
    }

    public function test_create_interval_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/carbon-interval-create.php'], [[
            'CarbonInterval now supports float values in Carbon 3.',
            9,
            'Verify that fractional intervals behave as expected.',
        ]]);
    }

    public function test_unrelated_interval_static_call_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/carbon-interval-safe.php'], []);
    }
}
