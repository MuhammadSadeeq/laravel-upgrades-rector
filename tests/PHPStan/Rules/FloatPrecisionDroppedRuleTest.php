<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\FloatPrecisionDroppedRule;

/** @extends Laravel11RuleTestCase<FloatPrecisionDroppedRule> */
final class FloatPrecisionDroppedRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): FloatPrecisionDroppedRule
    {
        return new FloatPrecisionDroppedRule;
    }

    public function test_float_precision_arguments_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/float-precision-positive.php'], [[
            'Float() no longer accepts precision/scale arguments in Laravel 11.',
            9,
            'Use decimal(\'column\', 8, 2) for fixed precision or float(\'column\', precision: N).',
        ]]);
    }

    public function test_double_precision_arguments_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/float-precision-double.php'], [[
            'Double() no longer accepts precision/scale arguments in Laravel 11.',
            9,
            'Use decimal(\'column\', 8, 2) for fixed precision or float(\'column\', precision: N).',
        ]]);
    }

    public function test_single_argument_and_unrelated_receiver_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/float-precision-safe.php'], []);
    }

    public function test_named_or_single_precision_argument_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/float-precision-named.php'], []);
    }

    public function test_legacy_named_precision_arguments_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/float-precision-legacy-named.php'], [
            [
                'Float() no longer accepts precision/scale arguments in Laravel 11.',
                9,
                'Use decimal(\'column\', 8, 2) for fixed precision or float(\'column\', precision: N).',
            ],
            [
                'Float() no longer accepts precision/scale arguments in Laravel 11.',
                10,
                'Use decimal(\'column\', 8, 2) for fixed precision or float(\'column\', precision: N).',
            ],
            [
                'Float() no longer accepts precision/scale arguments in Laravel 11.',
                11,
                'Use decimal(\'column\', 8, 2) for fixed precision or float(\'column\', precision: N).',
            ],
        ]);
    }
}
