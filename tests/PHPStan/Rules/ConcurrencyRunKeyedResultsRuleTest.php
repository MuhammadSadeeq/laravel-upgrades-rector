<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ConcurrencyRunKeyedResultsRule;

/** @extends Laravel12RuleTestCase<ConcurrencyRunKeyedResultsRule> */
final class ConcurrencyRunKeyedResultsRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): ConcurrencyRunKeyedResultsRule
    {
        return new ConcurrencyRunKeyedResultsRule;
    }

    public function test_keyed_assignment_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/concurrency-keyed-assignment.php'], [[
            'Concurrency::run() now preserves associative keys in Laravel 12.',
            9,
            'Update result handling if it assumes numeric indexes; associative keys are retained.',
        ]]);
    }

    public function test_keyed_calls_in_argument_and_return_positions_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/concurrency-keyed-positions.php'], [
            [
                'Concurrency::run() now preserves associative keys in Laravel 12.',
                11,
                'Update result handling if it assumes numeric indexes; associative keys are retained.',
            ],
            [
                'Concurrency::run() now preserves associative keys in Laravel 12.',
                15,
                'Update result handling if it assumes numeric indexes; associative keys are retained.',
            ],
        ]);
    }

    public function test_indexed_tasks_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/concurrency-indexed-safe.php'], []);
    }

    public function test_unrelated_namespaced_concurrency_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/concurrency-unrelated.php'], []);
    }
}
