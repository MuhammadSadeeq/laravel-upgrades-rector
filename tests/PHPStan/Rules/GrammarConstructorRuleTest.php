<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\GrammarConstructorRule;

/** @extends Laravel12RuleTestCase<GrammarConstructorRule> */
final class GrammarConstructorRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): GrammarConstructorRule
    {
        return new GrammarConstructorRule;
    }

    public function test_grammar_without_connection_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/grammar-constructor-positive.php'], [[
            'Laravel 12 grammar constructors require a Connection argument.',
            10,
            'Pass the Connection instance when constructing a query grammar.',
        ]]);
    }

    public function test_grammar_with_connection_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/grammar-constructor-other.php'], []);
    }

    public function test_unrelated_grammar_named_class_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/grammar-constructor-safe.php'], []);
    }
}
