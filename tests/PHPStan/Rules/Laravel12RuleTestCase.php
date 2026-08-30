<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @template TRule of Rule
 *
 * @extends RuleTestCase<TRule>
 */
abstract class Laravel12RuleTestCase extends RuleTestCase
{
    protected function setUp(): void
    {
        if (getenv('LARAVEL_ENV') !== '12') {
            self::markTestSkipped('Laravel 12 PHPStan rule tests require LARAVEL_ENV=12.');
        }

        parent::setUp();
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return array_values(array_merge(parent::getAdditionalConfigFiles(), [dirname(__DIR__).'/rule-tests.neon']));
    }
}
