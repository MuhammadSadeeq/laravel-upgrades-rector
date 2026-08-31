<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\StrFactoryResetRule;

/** @extends Laravel13RuleTestCase<StrFactoryResetRule> */
final class StrFactoryResetRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): StrFactoryResetRule
    {
        return new StrFactoryResetRule;
    }

    public function test_random_string_factory_mutation_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/str-factory-random.php'], [[
            'Custom Str factories are reset between tests in Laravel 13.',
            9,
            'Register UUID, ULID, and random-string factories in each relevant test or setup hook.',
        ]]);
    }

    public function test_uuid_and_ulid_factory_calls_in_all_positions_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/str-factory-uuid-ulid.php'], [
            [
                'Custom Str factories are reset between tests in Laravel 13.',
                9,
                'Register UUID, ULID, and random-string factories in each relevant test or setup hook.',
            ],
            [
                'Custom Str factories are reset between tests in Laravel 13.',
                14,
                'Register UUID, ULID, and random-string factories in each relevant test or setup hook.',
            ],
        ]);
    }

    public function test_normal_reset_and_unrelated_static_calls_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/str-factory-safe.php'], []);
    }

    public function test_application_code_factory_setup_is_safe(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel13-str-app-');
        self::assertIsString($path);

        file_put_contents($path, <<<'PHP'
<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Support\Str;

function configureApplicationFactory(): void
{
    Str::createUuidsUsing(static fn (): mixed => null);
}
PHP
        );

        try {
            $this->analyse([$path], []);
        } finally {
            unlink($path);
        }
    }
}
