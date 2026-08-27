<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\EmailVerificationAutoRegistrationRule;

/** @extends Laravel11RuleTestCase<EmailVerificationAutoRegistrationRule> */
final class EmailVerificationAutoRegistrationRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): EmailVerificationAutoRegistrationRule
    {
        return new EmailVerificationAutoRegistrationRule;
    }

    public function test_empty_event_provider_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/email-verification-positive.php'], [[
            'Laravel 11 auto-registers SendEmailVerificationNotification from EventServiceProvider.',
            7,
            'Review custom Registered listeners; define configureEmailVerification() if you need to opt out.',
        ]]);
    }

    public function test_explicit_configuration_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/email-verification-configured.php'], []);
    }

    public function test_explicit_registered_listener_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/email-verification-listener.php'], []);
    }
}
