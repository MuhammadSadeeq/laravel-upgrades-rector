<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\MorphPivotTableNameRule;

/** @extends Laravel13RuleTestCase<MorphPivotTableNameRule> */
final class MorphPivotTableNameRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): MorphPivotTableNameRule
    {
        return new MorphPivotTableNameRule;
    }

    public function test_morph_to_many_on_typed_model_is_reported(): void
    {
        require_once __DIR__.'/Fixture/morph-to-many.php';

        $this->analyse([__DIR__.'/Fixture/morph-to-many.php'], [[
            'Custom MorphPivot/Pivot table names are pluralized differently in Laravel 13.',
            14,
            'Define protected $table on the custom pivot model to preserve its previous table name.',
        ]]);
    }

    public function test_inverse_morph_call_in_argument_position_is_reported(): void
    {
        require_once __DIR__.'/Fixture/morph-pivot-positions.php';

        $this->analyse([__DIR__.'/Fixture/morph-pivot-positions.php'], [[
            'Custom MorphPivot/Pivot table names are pluralized differently in Laravel 13.',
            13,
            'Define protected $table on the custom pivot model to preserve its previous table name.',
        ]]);
    }

    public function test_explicit_table_and_unrelated_receivers_are_safe(): void
    {
        require_once __DIR__.'/Fixture/morph-pivot-safe.php';

        $this->analyse([__DIR__.'/Fixture/morph-pivot-safe.php'], []);
    }
}
