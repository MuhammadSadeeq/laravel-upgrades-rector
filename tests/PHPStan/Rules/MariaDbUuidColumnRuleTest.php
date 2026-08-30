<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\MariaDbUuidColumnRule;

/** @extends Laravel11RuleTestCase<MariaDbUuidColumnRule> */
final class MariaDbUuidColumnRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): MariaDbUuidColumnRule
    {
        return new MariaDbUuidColumnRule;
    }

    public function test_blueprint_uuid_column_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/mariadb-uuid-positive.php'], [[
            'The new MariaDB driver creates native UUID columns for uuid(); the column type differs from MySQL.',
            9,
            'Use char(36) instead if you switch to the mariadb driver and need the previous behaviour.',
        ]]);
    }

    public function test_uuid_without_blueprint_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/mariadb-uuid-safe.php'], []);
    }

    public function test_other_blueprint_column_method_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/mariadb-uuid-other-column.php'], []);
    }
}
