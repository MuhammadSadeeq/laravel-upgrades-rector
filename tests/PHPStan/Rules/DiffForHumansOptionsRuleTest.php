<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\DiffForHumansOptionsRule;

/** @extends Laravel11RuleTestCase<DiffForHumansOptionsRule> */
final class DiffForHumansOptionsRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): DiffForHumansOptionsRule
    {
        return new DiffForHumansOptionsRule;
    }

    public function test_typed_carbon_diff_for_humans_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/diff-humans-positive.php'], [[
            'diffForHumans() option handling changed in Carbon 3; verify the output format.',
            9,
            'Verify parts, short syntax, and locale handling produce the expected result.',
        ]]);
    }

    public function test_diff_for_humans_with_options_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/diff-humans-options.php'], [[
            'diffForHumans() option handling changed in Carbon 3; verify the output format.',
            9,
            'Verify parts, short syntax, and locale handling produce the expected result.',
        ]]);
    }

    public function test_unrelated_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/diff-humans-safe.php'], []);
    }
}
