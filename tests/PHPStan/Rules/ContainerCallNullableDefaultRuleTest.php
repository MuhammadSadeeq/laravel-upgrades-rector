<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ContainerCallNullableDefaultRule;

/** @extends Laravel13RuleTestCase<ContainerCallNullableDefaultRule> */
final class ContainerCallNullableDefaultRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): ContainerCallNullableDefaultRule
    {
        return new ContainerCallNullableDefaultRule;
    }

    public function test_contract_typed_container_call_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/container-call-contract.php'], [[
            'Container::call() now respects nullable class-typed default parameters when resolving.',
            9,
            'Verify that nullable constructor parameters resolve to the expected value.',
        ]]);
    }

    public function test_concrete_typed_container_calls_in_all_positions_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/container-call-positions.php'], [
            [
                'Container::call() now respects nullable class-typed default parameters when resolving.',
                10,
                'Verify that nullable constructor parameters resolve to the expected value.',
            ],
            [
                'Container::call() now respects nullable class-typed default parameters when resolving.',
                15,
                'Verify that nullable constructor parameters resolve to the expected value.',
            ],
        ]);
    }

    public function test_unrelated_and_non_variable_receivers_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/container-call-safe.php'], []);
    }
}
