<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\SchemaInspectionAllSchemasRule;

/** @extends Laravel12RuleTestCase<SchemaInspectionAllSchemasRule> */
final class SchemaInspectionAllSchemasRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): SchemaInspectionAllSchemasRule
    {
        return new SchemaInspectionAllSchemasRule;
    }

    public function test_static_inspection_without_schema_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/schema-inspection-positive.php'], [
            [
                'Schema inspection methods now return results from all schemas by default.',
                9,
                'Pass a schema name to limit results to a specific schema.',
            ],
            [
                'Schema inspection methods now return results from all schemas by default.',
                10,
                'Pass a schema name to limit results to a specific schema.',
            ],
            [
                'Schema inspection methods now return results from all schemas by default.',
                11,
                'Pass a schema name to limit results to a specific schema.',
            ],
            [
                'Schema inspection methods now return results from all schemas by default.',
                12,
                'Pass a schema name to limit results to a specific schema.',
            ],
        ]);
    }

    public function test_explicit_schema_arguments_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/schema-inspection-safe.php'], []);
    }

    public function test_schema_connection_and_typed_builder_calls_are_reported_but_safe_calls_are_ignored(): void
    {
        $this->analyse([__DIR__.'/Fixture/schema-inspection-edge.php'], [[
            'Schema inspection methods now return results from all schemas by default.',
            10,
            'Pass a schema name to limit results to a specific schema.',
        ], [
            'Schema inspection methods now return results from all schemas by default.',
            16,
            'Pass a schema name to limit results to a specific schema.',
        ], [
            'Schema inspection methods now return results from all schemas by default.',
            17,
            'Pass a schema name to limit results to a specific schema.',
        ]]);
    }
}
