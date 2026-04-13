<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePasswordRehashingRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: Auto password rehashing may require authPasswordName';

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $classReflection->is('Illuminate\\Contracts\\Auth\\Authenticatable')) {
            return null;
        }

        if ($this->hasPasswordNameConfiguration($node)) {
            return null;
        }

        if (! $this->hasCustomPasswordAccessor($node)) {
            return null;
        }

        $passwordColumn = $this->resolveCustomPasswordColumn($node);

        if ($passwordColumn !== null) {
            array_unshift($node->stmts, new Property(
                Class_::MODIFIER_PROTECTED,
                [
                    new PropertyProperty('authPasswordName', new String_($passwordColumn)),
                ],
            ));

            return $node;
        }

        if ($this->hasPasswordRehashingComment($node)) {
            return null;
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. This model overrides getAuthPassword(); if the password column is not "password", set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php'),
        ], $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function hasPasswordNameConfiguration(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                if (! $prop instanceof PropertyProperty) {
                    continue;
                }

                if ($prop->name->name === 'authPasswordName') {
                    return true;
                }
            }
        }

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof ClassMethod) {
                continue;
            }

            if ($stmt->name->name === 'getAuthPasswordName') {
                return true;
            }
        }

        return false;
    }

    private function hasCustomPasswordAccessor(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof ClassMethod) {
                continue;
            }

            if ($stmt->name->name === 'getAuthPassword') {
                return true;
            }
        }

        return false;
    }

    private function resolveCustomPasswordColumn(Class_ $class): ?string
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof ClassMethod || $stmt->name->name !== 'getAuthPassword') {
                continue;
            }

            if ($stmt->stmts === null) {
                return null;
            }

            foreach ($stmt->stmts as $methodStmt) {
                if (! $methodStmt instanceof Return_ || ! $methodStmt->expr instanceof PropertyFetch) {
                    continue;
                }

                if (! $methodStmt->expr->var instanceof Variable || ! $this->isName($methodStmt->expr->var, 'this')) {
                    continue;
                }

                $propertyName = $this->getName($methodStmt->expr->name);

                if ($propertyName === null || $propertyName === 'password') {
                    return null;
                }

                return $propertyName;
            }
        }

        return null;
    }

    private function hasPasswordRehashingComment(Class_ $class): bool
    {
        foreach ($class->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn about password rehashing changes for Authenticatable classes that override getAuthPassword in Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class User extends Authenticatable
{
    public function getAuthPassword(): string
    {
        return $this->custom_password_field;
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 11: Auto password rehashing may require authPasswordName. This model overrides getAuthPassword(); if the password column is not "password", set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php
class User extends Authenticatable
{
    public function getAuthPassword(): string
    {
        return $this->custom_password_field;
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
