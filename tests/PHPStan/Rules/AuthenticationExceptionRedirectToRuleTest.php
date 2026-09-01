<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\AuthenticationExceptionRedirectToRule;

/** @extends Laravel11RuleTestCase<AuthenticationExceptionRedirectToRule> */
final class AuthenticationExceptionRedirectToRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): AuthenticationExceptionRedirectToRule
    {
        return new AuthenticationExceptionRedirectToRule;
    }

    public function test_no_argument_redirect_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/authentication-redirect-positive.php'], [[
            'AuthenticationException::redirectTo() now requires a Request argument in Laravel 11.',
            9,
            'Pass a Request instance to redirectTo(); no safe typed request variable was available to the Rector transform.',
        ]]);
    }

    public function test_request_argument_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/authentication-redirect-safe.php'], []);
    }

    public function test_unrelated_redirect_method_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/authentication-redirect-unrelated.php'], []);
    }
}
