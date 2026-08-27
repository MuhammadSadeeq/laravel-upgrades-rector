<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\ArrayFirstLastPolyfillRule;

/** @extends Laravel13RuleTestCase<ArrayFirstLastPolyfillRule> */
final class ArrayFirstLastPolyfillRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): ArrayFirstLastPolyfillRule
    {
        return new ArrayFirstLastPolyfillRule;
    }

    public function test_global_helper_declarations_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/array-first-last-declaration.php'], [
            [
                'The array_first function declaration conflicts with the PHP 8.5 array polyfill in Laravel 13.',
                3,
                'Rename the global helper, or use Illuminate\\Support\\Arr::first for callback semantics.',
            ],
            [
                'The array_last function declaration conflicts with the PHP 8.5 array polyfill in Laravel 13.',
                8,
                'Rename the global helper, or use Illuminate\\Support\\Arr::last for callback semantics.',
            ],
        ]);
    }

    public function test_callback_calls_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/array-first-last-callback.php'], [
            [
                'The array_first callback call conflicts with the PHP 8.5 array polyfill in Laravel 13.',
                5,
                'Rename the global helper, or use Illuminate\\Support\\Arr::first for callback semantics.',
            ],
            [
                'The array_last callback call conflicts with the PHP 8.5 array polyfill in Laravel 13.',
                6,
                'Rename the global helper, or use Illuminate\\Support\\Arr::last for callback semantics.',
            ],
        ]);
    }

    public function test_namespaced_helper_and_plain_call_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/array-first-last-safe.php'], []);
    }

    public function test_namespaced_unqualified_callback_calls_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/array-first-last-namespaced-callback.php'], [
            [
                'The array_first callback call conflicts with the PHP 8.5 array polyfill in Laravel 13.',
                7,
                'Rename the global helper, or use Illuminate\\Support\\Arr::first for callback semantics.',
            ],
            [
                'The array_last callback call conflicts with the PHP 8.5 array polyfill in Laravel 13.',
                8,
                'Rename the global helper, or use Illuminate\\Support\\Arr::last for callback semantics.',
            ],
        ]);
    }
}
