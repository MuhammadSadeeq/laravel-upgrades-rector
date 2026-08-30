<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ColumnChangeRequiresModifiersRule;

/** @extends Laravel11RuleTestCase<ColumnChangeRequiresModifiersRule> */
final class ColumnChangeRequiresModifiersRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): ColumnChangeRequiresModifiersRule
    {
        return new ColumnChangeRequiresModifiersRule;
    }

    public function test_blueprint_change_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/column-change-positive.php'], [[
            'Column modification via ->change() requires all column modifiers to be re-specified in Laravel 11+.',
            9,
            'Re-specify modifiers explicitly or use schema:dump to capture the current state.',
        ]]);
    }

    public function test_index_modifier_change_gets_specific_guidance(): void
    {
        $this->analyse([__DIR__.'/Fixture/column-change-index.php'], [[
            'Column modification via ->change() requires all column modifiers to be re-specified in Laravel 11+. Index modifiers (->primary()/->unique()/->index()) are NOT preserved by ->change().',
            9,
            'Drop the column and re-add it with all modifiers, or use DB::statement for precise DDL.',
        ]]);
    }

    public function test_unrelated_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/column-change-safe.php'], []);
    }
}
