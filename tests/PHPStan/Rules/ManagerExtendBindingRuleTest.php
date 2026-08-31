<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ManagerExtendBindingRule;

/** @extends Laravel13RuleTestCase<ManagerExtendBindingRule> */
final class ManagerExtendBindingRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): ManagerExtendBindingRule
    {
        return new ManagerExtendBindingRule;
    }

    public function test_typed_manager_extend_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/manager-extend-typed.php'], [[
            'Manager extend() callbacks now receive the container instance in Laravel 13.',
            9,
            'Update the closure signature to accept the container or use the passed manager instance.',
        ]]);
    }

    public function test_facade_extend_calls_in_all_positions_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/manager-extend-facades.php'], [
            [
                'Manager extend() callbacks now receive the container instance in Laravel 13.',
                12,
                'Update the closure signature to accept the container or use the passed manager instance.',
            ],
            [
                'Manager extend() callbacks now receive the container instance in Laravel 13.',
                17,
                'Update the closure signature to accept the container or use the passed manager instance.',
            ],
            [
                'Manager extend() callbacks now receive the container instance in Laravel 13.',
                22,
                'Update the closure signature to accept the container or use the passed manager instance.',
            ],
            [
                'Manager extend() callbacks now receive the container instance in Laravel 13.',
                27,
                'Update the closure signature to accept the container or use the passed manager instance.',
            ],
        ]);
    }

    public function test_redis_manager_extend_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/manager-extend-redis.php'], [[
            'Manager extend() callbacks now receive the container instance in Laravel 13.',
            9,
            'Update the closure signature to accept the container or use the passed manager instance.',
        ]]);
    }

    public function test_unrelated_manager_like_method_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/manager-extend-safe.php'], []);
    }
}
