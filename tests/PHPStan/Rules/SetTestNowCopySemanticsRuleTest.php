<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\SetTestNowCopySemanticsRule;

/** @extends Laravel11RuleTestCase<SetTestNowCopySemanticsRule> */
final class SetTestNowCopySemanticsRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): SetTestNowCopySemanticsRule
    {
        return new SetTestNowCopySemanticsRule;
    }

    public function test_carbon_set_test_now_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/set-test-now-carbon.php'], [[
            'Carbon 3 setTestNow() stores a copy of the date, not a reference.',
            10,
            'Verify that tests relying on shared state still pass with the copy semantics.',
        ]]);
    }

    public function test_carbon_immutable_set_test_now_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/set-test-now-immutable.php'], [[
            'Carbon 3 setTestNow() stores a copy of the date, not a reference.',
            10,
            'Verify that tests relying on shared state still pass with the copy semantics.',
        ]]);
    }

    public function test_unrelated_static_set_test_now_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/set-test-now-safe.php'], []);
    }
}
