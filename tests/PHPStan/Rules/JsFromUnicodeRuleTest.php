<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\JsFromUnicodeRule;

/** @extends Laravel13RuleTestCase<JsFromUnicodeRule> */
final class JsFromUnicodeRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): JsFromUnicodeRule
    {
        return new JsFromUnicodeRule;
    }

    public function test_imported_js_from_call_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/js-from-imported.php'], [[
            'Js::from() Unicode escaping behaviour changed in Laravel 13.',
            9,
            'Verify that the output HTML/JSON handles the new escaping correctly.',
        ]]);
    }

    public function test_fully_qualified_js_from_call_in_nested_position_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/js-from-positions.php'], [
            [
                'Js::from() Unicode escaping behaviour changed in Laravel 13.',
                9,
                'Verify that the output HTML/JSON handles the new escaping correctly.',
            ],
            [
                'Js::from() Unicode escaping behaviour changed in Laravel 13.',
                19,
                'Verify that the output HTML/JSON handles the new escaping correctly.',
            ],
        ]);
    }

    public function test_unrelated_from_calls_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/js-from-safe.php'], []);
    }
}
