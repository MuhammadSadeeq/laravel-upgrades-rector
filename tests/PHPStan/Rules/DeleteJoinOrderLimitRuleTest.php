<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\DeleteJoinOrderLimitRule;

/** @extends Laravel13RuleTestCase<DeleteJoinOrderLimitRule> */
final class DeleteJoinOrderLimitRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): DeleteJoinOrderLimitRule
    {
        return new DeleteJoinOrderLimitRule;
    }

    public function test_joined_delete_with_order_by_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/delete-join-order.php'], [[
            'DELETE with JOIN and ORDER BY/LIMIT is not supported by MySQL/MariaDB.',
            9,
            'Use a subquery to select IDs first, then delete those IDs.',
        ]]);
    }

    public function test_joined_delete_with_limit_and_nested_position_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/delete-join-limit-positions.php'], [
            [
                'DELETE with JOIN and ORDER BY/LIMIT is not supported by MySQL/MariaDB.',
                9,
                'Use a subquery to select IDs first, then delete those IDs.',
            ],
            [
                'DELETE with JOIN and ORDER BY/LIMIT is not supported by MySQL/MariaDB.',
                16,
                'Use a subquery to select IDs first, then delete those IDs.',
            ],
        ]);
    }

    public function test_missing_join_or_ordering_and_unrelated_chains_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/delete-join-safe.php'], []);
    }
}
