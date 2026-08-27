<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\EloquentCastsMethodConflictRule;

/** @extends Laravel11RuleTestCase<EloquentCastsMethodConflictRule> */
final class EloquentCastsMethodConflictRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): EloquentCastsMethodConflictRule
    {
        return new EloquentCastsMethodConflictRule;
    }

    public function test_relationship_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/eloquent-casts-positive.php'], [[
            'This Eloquent model defines a relationship named casts(), which conflicts with the Laravel 11 Model API.',
            7,
            'Rename the relationship method and update its call sites.',
        ]]);
    }

    public function test_array_casts_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/eloquent-casts-array.php'], []);
    }

    public function test_unrelated_class_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/eloquent-casts-unrelated.php'], []);
    }
}
