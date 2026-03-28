<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\PropertyItem;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePasswordRehashingRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: Auto password rehashing is now enabled';

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

        if (! $this->hasCustomPasswordField($node)) {
            return null;
        }

        $existingComments = $node->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// ' . self::COMMENT_MARKER
            . '. If using a custom password field, set protected $authPasswordName. '
            . 'To disable: rehash_on_login => false in config/hashing.php'
        );
        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function hasCustomPasswordField(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                if (! $prop instanceof PropertyItem) {
                    continue;
                }

                $name = $prop->name->name;

                // Check for the specific Laravel property that indicates custom password field
                if ($name === 'authPasswordName') {
                    return true;
                }
            }
        }

        // Also check for getAuthPasswordName method that returns something other than 'password'
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof ClassMethod) {
                continue;
            }

            if ($stmt->name->name !== 'getAuthPasswordName') {
                continue;
            }

            // If they have a custom getAuthPasswordName, they have a custom password field
            return true;
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn about password rehashing changes for Authenticatable classes with custom password fields in Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class User extends Authenticatable
{
    protected $custom_password_field;
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 11: Auto password rehashing is now enabled. If using a custom password field, set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php
class User extends Authenticatable
{
    protected $custom_password_field;
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
