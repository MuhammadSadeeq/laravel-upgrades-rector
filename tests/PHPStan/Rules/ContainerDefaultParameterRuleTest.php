<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ContainerDefaultParameterRule;

/** @extends Laravel12RuleTestCase<ContainerDefaultParameterRule> */
final class ContainerDefaultParameterRuleTest extends Laravel12RuleTestCase
{
    protected function getRule(): ContainerDefaultParameterRule
    {
        return new ContainerDefaultParameterRule;
    }

    public function test_container_resolved_framework_classes_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/ContainerNullableCommand.php'], [[
            'Laravel 12 container resolution now honours null defaults on class-typed constructor parameters.',
            10,
            'Review this nullable dependency: the container now keeps the null default instead of resolving the class.',
        ]]);

        $this->analyse([__DIR__.'/Fixture/ContainerNullableJob.php'], [[
            'Laravel 12 container resolution now honours null defaults on class-typed constructor parameters.',
            10,
            'Review this nullable dependency: the container now keeps the null default instead of resolving the class.',
        ]]);
    }

    public function test_handler_classes_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/Listeners/NullableListener.php'], [[
            'Laravel 12 container resolution now honours null defaults on class-typed constructor parameters.',
            9,
            'Review this nullable dependency: the container now keeps the null default instead of resolving the class.',
        ]]);
    }

    public function test_scalar_and_unrelated_classes_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/ContainerScalarCommand.php'], []);
        $this->analyse([__DIR__.'/Fixture/ContainerOrdinaryService.php'], []);
    }
}
