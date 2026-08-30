<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\PassportRoutesRemovedRule;

/** @extends Laravel11RuleTestCase<PassportRoutesRemovedRule> */
final class PassportRoutesRemovedRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): PassportRoutesRemovedRule
    {
        return new PassportRoutesRemovedRule;
    }

    public function test_imported_passport_routes_call_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/passport-routes-imported.php'], [[
            'Passport::routes() was removed in Passport 12. Routes are now auto-registered.',
            9,
            'Remove the call; run vendor:publish --tag=passport-migrations.',
        ]]);
    }

    public function test_fully_qualified_passport_routes_call_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/passport-routes-qualified.php'], [[
            'Passport::routes() was removed in Passport 12. Routes are now auto-registered.',
            9,
            'Remove the call; run vendor:publish --tag=passport-migrations.',
        ]]);
    }

    public function test_unrelated_static_routes_call_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/passport-routes-safe.php'], []);
    }
}
