<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\PasswordResetSubjectRule;

/** @extends Laravel13RuleTestCase<PasswordResetSubjectRule> */
final class PasswordResetSubjectRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): PasswordResetSubjectRule
    {
        return new PasswordResetSubjectRule;
    }

    public function test_message_and_identifier_are_for_laravel_13(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__.'/Fixture/password-reset-subject.php']);

        self::assertCount(1, $errors);
        self::assertSame('The default password reset email subject changed in Laravel 13.', $errors[0]->getMessage());
        self::assertSame('laravelUpgrade.passwordResetSubject', $errors[0]->getIdentifier());
        self::assertSame('Override the subject() method on ResetPassword notification to customise.', $errors[0]->getTip());
        self::assertSame(10, $errors[0]->getLine());
    }
}
