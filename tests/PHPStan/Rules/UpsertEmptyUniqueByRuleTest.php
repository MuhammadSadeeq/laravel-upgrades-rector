<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\UpsertEmptyUniqueByRule;

/** @extends Laravel13RuleTestCase<UpsertEmptyUniqueByRule> */
final class UpsertEmptyUniqueByRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): UpsertEmptyUniqueByRule
    {
        return new UpsertEmptyUniqueByRule;
    }

    public function test_query_builder_empty_array_unique_by_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/upsert-empty-array.php'], [[
            'upsert() with an empty uniqueBy array is not supported by MySQL/MariaDB.',
            9,
            'Provide the actual unique column names as the second argument.',
        ]]);
    }

    public function test_eloquent_builder_empty_string_and_named_unique_by_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/upsert-empty-positions.php'], [
            [
                'upsert() with an empty uniqueBy array is not supported by MySQL/MariaDB.',
                9,
                'Provide the actual unique column names as the second argument.',
            ],
            [
                'upsert() with an empty uniqueBy array is not supported by MySQL/MariaDB.',
                14,
                'Provide the actual unique column names as the second argument.',
            ],
        ]);
    }

    public function test_non_empty_dynamic_and_unrelated_upserts_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/upsert-safe.php'], []);
    }
}
