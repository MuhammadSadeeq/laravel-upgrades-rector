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

    public function test_old_subject_in_mail_message_is_reported(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__.'/Fixture/password-reset-subject.php']);

        self::assertCount(1, $errors);
        self::assertSame('The default password reset subject changed from "Reset Password Notification" to "Reset your password" in Laravel 13.', $errors[0]->getMessage());
        self::assertSame('laravelUpgrade.passwordResetSubject', $errors[0]->getIdentifier());
        self::assertSame('Update tests, assertions, or translation overrides from "Reset Password Notification" to "Reset your password".', $errors[0]->getTip());
        self::assertSame(9, $errors[0]->getLine());
    }

    public function test_new_subject_and_unrelated_strings_are_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/password-reset-subject-safe.php'], []);
    }

    public function test_old_subject_in_array_and_return_positions_is_reported(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__.'/Fixture/password-reset-subject-edge.php']);

        self::assertCount(2, $errors);
        self::assertSame('laravelUpgrade.passwordResetSubject', $errors[0]->getIdentifier());
        self::assertSame('Update tests, assertions, or translation overrides from "Reset Password Notification" to "Reset your password".', $errors[0]->getTip());
        self::assertSame(9, $errors[0]->getLine());
        self::assertSame('laravelUpgrade.passwordResetSubject', $errors[1]->getIdentifier());
        self::assertSame('Update tests, assertions, or translation overrides from "Reset Password Notification" to "Reset your password".', $errors[1]->getTip());
        self::assertSame(14, $errors[1]->getLine());
    }
}
