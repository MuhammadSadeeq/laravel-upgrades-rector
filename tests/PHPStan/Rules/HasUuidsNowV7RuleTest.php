<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\HasUuidsNowV7Rule;

/** @extends Laravel12RuleTestCase<HasUuidsNowV7Rule> */
final class HasUuidsNowV7RuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): HasUuidsNowV7Rule
    {
        return new HasUuidsNowV7Rule;
    }

    public function test_has_uuids_trait_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/has-uuids-positive.php'], [[
            'HasUuids now generates UUIDv7 by default in Laravel 12.',
            10,
            'Switch to Illuminate\\Database\\Eloquent\\Concerns\\HasVersion4Uuids if you need the previous ordered UUIDv4 behaviour.',
        ]]);
    }

    public function test_has_version_four_alias_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/has-uuids-version-four-alias.php'], []);
    }

    public function test_unrelated_uuid_trait_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/has-uuids-unrelated.php'], []);
    }
}
