<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\PasswordRehashCustomColumnRule;

/** @extends Laravel11RuleTestCase<PasswordRehashCustomColumnRule> */
final class PasswordRehashCustomColumnRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): PasswordRehashCustomColumnRule
    {
        return new PasswordRehashCustomColumnRule;
    }

    public function test_custom_auth_password_column_without_name_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/password-rehash-positive.php'], [[
            'This model overrides getAuthPassword() but does not set protected $authPasswordName.',
            7,
            'Set protected $authPasswordName to the credential column name for auto-rehashing.',
        ]]);
    }

    public function test_default_password_column_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/password-rehash-default.php'], []);
    }

    public function test_custom_column_with_auth_password_name_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/password-rehash-configured.php'], []);
    }

    public function test_dynamic_password_accessor_is_reported_for_manual_review(): void
    {
        $this->analyse([__DIR__.'/Fixture/password-rehash-dynamic.php'], [[
            'This model dynamically determines getAuthPassword(); review $authPasswordName for Laravel 11 password rehashing (low-confidence).',
            7,
            'Set protected $authPasswordName or verify the credential column manually before enabling auto-rehashing.',
        ]]);
    }

    public function test_password_name_method_configures_the_accessor_safely(): void
    {
        $this->analyse([__DIR__.'/Fixture/password-rehash-name-method.php'], []);
    }
}
