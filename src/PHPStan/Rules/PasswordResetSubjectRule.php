<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: the default password reset subject changed from
 * "Reset Password" to "Reset Password Notification".
 *
 * @implements Rule<MethodCall>
 */
final class PasswordResetSubjectRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'subject') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'The default password reset email subject changed in Laravel 11.'
            )->identifier('laravelUpgrade.passwordResetSubject')
                ->tip('Override the subject() method on ResetPassword notification to customise.')
                ->build(),
        ];
    }
}
