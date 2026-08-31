<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ModelBootInstantiationRule;

/** @extends Laravel13RuleTestCase<ModelBootInstantiationRule> */
final class ModelBootInstantiationRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): ModelBootInstantiationRule
    {
        return new ModelBootInstantiationRule;
    }

    public function test_static_and_self_model_instantiation_in_boot_is_reported(): void
    {
        require_once __DIR__.'/Fixture/ModelBootSyntax/model-boot-static.php';

        $this->analyse([__DIR__.'/Fixture/ModelBootSyntax/model-boot-static.php'], [
            [
                'Model instantiation (static) inside boot() changed behaviour in Laravel 13.',
                13,
                'Move model creation out of boot() or use a static factory method.',
            ],
            [
                'Model instantiation (self) inside boot() changed behaviour in Laravel 13.',
                18,
                'Move model creation out of boot() or use a static factory method.',
            ],
        ]);
    }

    public function test_same_model_and_boot_with_traits_instantiation_is_reported(): void
    {
        require_once __DIR__.'/Fixture/ModelBootSyntax/model-boot-positions.php';

        $this->analyse([__DIR__.'/Fixture/ModelBootSyntax/model-boot-positions.php'], [
            [
                'Model instantiation (App\\BootedModel) inside boot() changed behaviour in Laravel 13.',
                13,
                'Move model creation out of boot() or use a static factory method.',
            ],
            [
                'Model instantiation (static) inside boot() changed behaviour in Laravel 13.',
                18,
                'Move model creation out of boot() or use a static factory method.',
            ],
        ]);
    }

    public function test_non_boot_model_methods_and_unrelated_new_objects_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/model-boot-safe.php'], []);
    }
}
