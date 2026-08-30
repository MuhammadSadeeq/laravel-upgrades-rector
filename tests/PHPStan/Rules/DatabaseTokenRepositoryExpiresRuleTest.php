<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\DatabaseTokenRepositoryExpiresRule;

/** @extends Laravel12RuleTestCase<DatabaseTokenRepositoryExpiresRule> */
final class DatabaseTokenRepositoryExpiresRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): DatabaseTokenRepositoryExpiresRule
    {
        return new DatabaseTokenRepositoryExpiresRule;
    }

    public function test_old_minute_based_expiry_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/database-token-repository-expires-positive.php'], [[
            'DatabaseTokenRepository $expires is now in seconds. The value 60 may be too short (was previously interpreted as minutes).',
            9,
            'Multiply the old minute value by 60 to convert to seconds.',
        ]]);
    }

    public function test_expiry_already_in_seconds_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/database-token-repository-expires-safe.php'], []);
    }

    public function test_boundary_and_dynamic_expiry_values_are_handled(): void
    {
        $this->analyse([__DIR__.'/Fixture/database-token-repository-expires-edge.php'], [[
            'DatabaseTokenRepository $expires is now in seconds. The value 599 may be too short (was previously interpreted as minutes).',
            10,
            'Multiply the old minute value by 60 to convert to seconds.',
        ]]);
    }
}
