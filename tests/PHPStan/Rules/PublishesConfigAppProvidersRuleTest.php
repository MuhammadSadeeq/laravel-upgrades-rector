<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\PublishesConfigAppProvidersRule;

/** @extends Laravel11RuleTestCase<PublishesConfigAppProvidersRule> */
final class PublishesConfigAppProvidersRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): PublishesConfigAppProvidersRule
    {
        return new PublishesConfigAppProvidersRule;
    }

    public function test_config_path_publish_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/publishes-app-config-positive.php'], [[
            'A package publishes config/app.php for provider registration, which conflicts with Laravel 11 bootstrap provider registration.',
            11,
            'Use ServiceProvider::addProviderToBootstrapFile() or publish a package-specific config file.',
        ]]);
    }

    public function test_other_config_publish_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/publishes-other-config.php'], []);
    }

    public function test_literal_config_app_publish_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/publishes-app-config-literal.php'], [[
            'A package publishes config/app.php for provider registration, which conflicts with Laravel 11 bootstrap provider registration.',
            11,
            'Use ServiceProvider::addProviderToBootstrapFile() or publish a package-specific config file.',
        ]]);
    }
}
