<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13: the default password reset subject changed from
 * "Reset Password Notification" to "Reset your password".
 *
 * @implements Rule<String_>
 */
final class PasswordResetSubjectRule implements Rule
{
    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof String_ || $node->value !== 'Reset Password Notification') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'The default password reset subject changed from "Reset Password Notification" to "Reset your password" in Laravel 13.'
            )->identifier('laravelUpgrade.passwordResetSubject')
                ->tip('Update tests, assertions, or translation overrides from "Reset Password Notification" to "Reset your password".')
                ->build(),
        ];
    }
}
