<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateEloquentCastsMethodRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: Base Eloquent model now defines a casts() method';

    /** @var array<int, string> */
    private array $relationshipMethods = [
        'hasMany',
        'hasOne',
        'belongsTo',
        'belongsToMany',
        'morphMany',
        'morphOne',
        'morphTo',
        'morphToMany',
        'morphedByMany',
        'hasManyThrough',
        'hasOneThrough',
    ];

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

        if (! $classReflection->is('Illuminate\\Database\\Eloquent\\Model')) {
            return null;
        }

        $castsMethod = $this->findCastsMethod($node);

        if (! $castsMethod instanceof ClassMethod) {
            return null;
        }

        if (! $this->hasRelationshipCallInBody($castsMethod)) {
            return null;
        }

        $existingComments = $node->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// ' . self::COMMENT_MARKER . '. '
            . 'If this casts() method is a relationship, rename it to avoid conflicts.'
        );
        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function findCastsMethod(Class_ $class): ?ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $this->isName($stmt->name, 'casts')) {
                return $stmt;
            }
        }

        return null;
    }

    private function hasRelationshipCallInBody(ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $found = false;

        $this->traverseNodesWithCallable($method->stmts, function (Node $subNode) use (&$found): ?Node {
            if ($subNode instanceof MethodCall) {
                $name = $this->getName($subNode->name);

                if ($name !== null && in_array($name, $this->relationshipMethods, true)) {
                    $found = true;
                }
            }

            return null;
        });

        return $found;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn about potential casts() method conflict with Eloquent base model in Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class User extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 11: Base Eloquent model now defines a casts() method. If this casts() method is a relationship, rename it to avoid conflicts.
class User extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
