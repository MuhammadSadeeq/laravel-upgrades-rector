<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\DoctrineRemovedMethodsRule;

/** @extends Laravel11RuleTestCase<DoctrineRemovedMethodsRule> */
final class DoctrineRemovedMethodsRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): DoctrineRemovedMethodsRule
    {
        return new DoctrineRemovedMethodsRule;
    }

    public function test_typed_connection_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/doctrine-methods-typed.php'], [[
            'getDoctrineColumn() was removed from Laravel 11 (high-confidence).',
            9,
            'Doctrine DBAL schema methods are gone; use Schema::getColumnType() or native schema inspection.',
        ]]);
    }

    public function test_unresolved_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/doctrine-methods-unresolved.php'], []);
    }

    public function test_unrelated_method_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/doctrine-methods-skip.php'], []);
    }
}
