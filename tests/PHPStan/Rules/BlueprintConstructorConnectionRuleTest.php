<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\BlueprintConstructorConnectionRule;

/** @extends Laravel12RuleTestCase<BlueprintConstructorConnectionRule> */
final class BlueprintConstructorConnectionRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): BlueprintConstructorConnectionRule
    {
        return new BlueprintConstructorConnectionRule;
    }

    public function test_non_connection_first_argument_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/blueprint-constructor-positive.php'], [[
            'Laravel 12 Blueprint constructors require a Connection as the first argument.',
            9,
            'Pass the schema Connection before the table name and callback.',
        ]]);
    }

    public function test_connection_first_argument_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/blueprint-constructor-safe.php'], []);
    }

    public function test_unknown_first_argument_is_reported_for_manual_review(): void
    {
        $this->analyse([__DIR__.'/Fixture/blueprint-constructor-unknown.php'], [[
            'Laravel 12 Blueprint constructors require a Connection as the first argument.',
            9,
            'Pass the schema Connection before the table name and callback.',
        ]]);
    }

    public function test_unrelated_namespaced_blueprint_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/blueprint-unrelated.php'], []);
    }
}
