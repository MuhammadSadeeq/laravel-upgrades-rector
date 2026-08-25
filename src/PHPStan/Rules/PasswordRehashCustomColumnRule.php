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
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: models overriding getAuthPassword() with a non-"password"
 * column must also define $authPasswordName, or password rehashing will
 * target the wrong column.
 */
/**
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
        $hasAuthPasswordName = false;

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod
                && $stmt->name->name === 'getAuthPassword') {
                // Check whether the body returns a non-'password' string.
                foreach ($stmt->stmts ?? [] as $inner) {
                    if ($inner instanceof Return_
                        && $inner->expr instanceof String_
                        && $inner->expr->value !== 'password') {
                        $hasCustomGetAuthPassword = true;
                    }
                }
            }

            if ($stmt instanceof Property) {
                foreach ($stmt->props as $propItem) {
                    if ($propItem->name->name === 'authPasswordName') {
                        $hasAuthPasswordName = true;
                    }
                }
            }
        }

        if (! $hasCustomGetAuthPassword || $hasAuthPasswordName) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'This model overrides getAuthPassword() but does not set protected $authPasswordName.'
            )->identifier('laravelUpgrade.passwordRehashCustomColumn')
                ->tip('Set protected $authPasswordName to the credential column name for auto-rehashing.')
                ->build(),
        ];
    }
}
