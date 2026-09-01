<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\EnumerableDumpSignatureRule;

/** @extends Laravel11RuleTestCase<EnumerableDumpSignatureRule> */
final class EnumerableDumpSignatureRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): EnumerableDumpSignatureRule
    {
        return new EnumerableDumpSignatureRule;
    }

    public function test_parameterized_dump_override_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/enumerable-dump-parameterized.php'], [[
            'Enumerable::dump() overrides must accept variadic arguments in Laravel 11.',
            7,
            'Review this parameterized dump() override and forward ...$args; the Rector leaves it unchanged.',
        ]]);
    }

    public function test_variadic_dump_override_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/enumerable-dump-variadic.php'], []);
    }

    public function test_unrelated_dump_override_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/enumerable-dump-unrelated.php'], []);
    }
}
