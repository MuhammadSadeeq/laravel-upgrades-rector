<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\CarbonUntypedDiffRule;

/** @extends Laravel11RuleTestCase<CarbonUntypedDiffRule> */
final class CarbonUntypedDiffRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): CarbonUntypedDiffRule
    {
        return new CarbonUntypedDiffRule;
    }

    public function test_untyped_diff_method_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/carbon-diff-untyped.php'], [[
            'diffIn*() returns a signed float in Carbon 3; verify this call handles negative values.',
            14,
            'Wrap with (int) abs(...) to preserve the old absolute-int behaviour.',
        ]]);
    }

    public function test_typed_carbon_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/carbon-diff-typed.php'], []);
    }

    public function test_non_diff_method_and_non_carbon_receiver_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/carbon-diff-safe.php'], []);
    }
}
