<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: models overriding getAuthPassword() with a non-"password"
 * column must also define $authPasswordName, or password rehashing will
 * target the wrong column.
 *
 * @implements Rule<Class_>
 */
final class PasswordRehashCustomColumnRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Class_ || $node->isAbstract() || $node->isAnonymous()) {
            return [];
        }

        $hasCustomGetAuthPassword = false;
        $hasDynamicGetAuthPassword = false;
        $hasAuthPasswordName = false;

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod
                && $stmt->name->name === 'getAuthPassword') {
                // Literal custom columns are high-confidence; expressions and
                // missing returns need a lower-confidence manual review.
                $hasReturn = false;
                foreach ($stmt->stmts ?? [] as $inner) {
                    if (! $inner instanceof Return_) {
                        continue;
                    }

                    $hasReturn = true;
                    if (! $inner->expr instanceof String_) {
                        $hasDynamicGetAuthPassword = true;
                    } elseif ($inner->expr->value !== 'password') {
                        $hasCustomGetAuthPassword = true;
                    }
                }

                if (! $hasReturn) {
                    $hasDynamicGetAuthPassword = true;
                }
            }

            if ($stmt instanceof ClassMethod
                && $stmt->name->name === 'getAuthPasswordName') {
                $hasAuthPasswordName = true;
            }

            if ($stmt instanceof Property) {
                foreach ($stmt->props as $propItem) {
                    if ($propItem->name->name === 'authPasswordName') {
                        $hasAuthPasswordName = true;
                    }
                }
            }
        }

        if ($hasAuthPasswordName) {
            return [];
        }

        if ($hasCustomGetAuthPassword) {
            return [$this->highConfidenceError()];
        }

        if (! $hasDynamicGetAuthPassword) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'This model dynamically determines getAuthPassword(); review $authPasswordName for Laravel 11 password rehashing (low-confidence).'
            )->identifier('laravelUpgrade.passwordRehashCustomColumn')
                ->tip('Set protected $authPasswordName or verify the credential column manually before enabling auto-rehashing.')
                ->metadata(['confidence' => 'low'])
                ->build(),
        ];
    }

    private function highConfidenceError(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'This model overrides getAuthPassword() but does not set protected $authPasswordName.'
        )->identifier('laravelUpgrade.passwordRehashCustomColumn')
            ->tip('Set protected $authPasswordName to the credential column name for auto-rehashing.')
            ->build();
    }
}
