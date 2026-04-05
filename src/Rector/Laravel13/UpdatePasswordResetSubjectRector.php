<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePasswordResetSubjectRector extends AbstractRector
{
    private const OLD_SUBJECT = 'Reset Password Notification';

    private const NEW_SUBJECT = 'Reset your password';

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_ || ! $this->isPasswordResetNotificationClass($node)) {
            return null;
        }

        $hasChanges = false;

        foreach ($node->getMethods() as $classMethod) {
            if (! $this->isName($classMethod->name, 'toMail') || $classMethod->stmts === null) {
                continue;
            }

            $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode) use (&$hasChanges): ?int {
                if (! $subNode instanceof MethodCall || ! $this->isName($subNode->name, 'subject')) {
                    return null;
                }

                $firstArgument = $subNode->args[0]->value ?? null;

                if (! $firstArgument instanceof String_ || $firstArgument->value !== self::OLD_SUBJECT) {
                    return null;
                }

                $firstArgument->value = self::NEW_SUBJECT;
                $hasChanges = true;

                return null;
            });
        }

        if (! $hasChanges) {
            return null;
        }

        return $node;
    }

    private function isPasswordResetNotificationClass(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        return $this->isName($class->extends, 'Notification')
            || $this->isName($class->extends, 'Illuminate\\Notifications\\Notification')
            || $this->isName($class->extends, 'ResetPassword')
            || $this->isName($class->extends, 'Illuminate\\Auth\\Notifications\\ResetPassword');
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update the default password reset subject string in password reset notifications for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ResetPasswordNotification extends Notification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage())->subject('Reset Password Notification');
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ResetPasswordNotification extends Notification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage())->subject('Reset your password');
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
