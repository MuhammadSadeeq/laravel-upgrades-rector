<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePasswordRehashingRector extends AbstractRector
{
    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

    private const COMMENT_MARKER = '@laravel-upgrade password-rehashing';

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if (! $this->isAuthenticatableClass($node)) {
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
                Modifiers::PROTECTED,
                [new PropertyItem('authPasswordName', new String_($passwordColumn))],
            ));

            return $node;
        }

        $added = $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'This model overrides getAuthPassword(); if the password column is not "password", '
            .'set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php.'
        );

        return $added ? $node : null;
    }

    private function hasPasswordNameConfiguration(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $propItem) {
                if (! $propItem instanceof PropertyItem) {
                    continue;
                }

                if ($propItem->name->name === 'authPasswordName') {
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

    private function isAuthenticatableClass(Class_ $class): bool
    {
        if ($this->isObjectType($class, new ObjectType('Illuminate\\Contracts\\Auth\\Authenticatable'))) {
            return true;
        }

        foreach ($class->implements as $implement) {
            if ($this->isName($implement, 'Illuminate\\Contracts\\Auth\\Authenticatable')
                || $implement->toString() === 'Authenticatable') {
                return true;
            }
        }

        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        return $this->isName($class->extends, 'Illuminate\\Foundation\\Auth\\User')
            || $this->isName($class->extends, 'Illuminate\\Database\\Eloquent\\Model')
            || in_array($class->extends->toString(), ['User', 'Authenticatable', 'Model'], true);
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
