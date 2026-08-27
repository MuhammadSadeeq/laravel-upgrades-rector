<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\SchemaGetColumnTypeRule;

/** @extends Laravel11RuleTestCase<SchemaGetColumnTypeRule> */
final class SchemaGetColumnTypeRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): SchemaGetColumnTypeRule
    {
        return new SchemaGetColumnTypeRule;
    }

    public function test_schema_facade_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/schema-column-type-static.php'], [[
            'Schema::getColumnType() now returns the native column type instead of the Doctrine DBAL equivalent.',
            9,
            'Review comparisons and mappings that expect Doctrine DBAL type names.',
        ]]);
    }

    public function test_schema_builder_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/schema-column-type-builder.php'], [[
            'Schema::getColumnType() now returns the native column type instead of the Doctrine DBAL equivalent.',
            9,
            'Review comparisons and mappings that expect Doctrine DBAL type names.',
        ]]);
    }

    public function test_unrelated_method_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/schema-column-type-skip.php'], []);
    }
}
