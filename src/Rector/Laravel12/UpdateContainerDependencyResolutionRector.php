<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateContainerDependencyResolutionRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof ClassMethod) {
            return null;
        }

        if (! $this->isName($node->name, '__construct')) {
            return null;
        }

        $hasAffectedParams = false;

        foreach ($node->params as $param) {
            if ($this->isAffectedParameter($param)) {
                $hasAffectedParams = true;
                break;
            }
        }

        if (! $hasAffectedParams) {
            return null;
        }

        $existingComments = $node->getComments();
        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 12:')) {
                return null;
            }
        }

        $newComment = new Comment(
            '// Laravel 12: Container now respects default parameter values when resolving dependencies. This constructor may behave differently.'
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));

        return $node;
    }

    private function isAffectedParameter(Param $param): bool
    {
        if ($param->default === null) {
            return false;
        }

        if ($param->type === null) {
            return false;
        }

        if ($param->type instanceof Name) {
            return true;
        }

        if ($param->type instanceof Node\NullableType) {
            return $param->type->type instanceof Name;
        }

        if ($param->type instanceof Node\UnionType) {
            foreach ($param->type->types as $type) {
                if ($type instanceof Name) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for container dependency resolution behavior change in Laravel 12',
            [
                new CodeSample(
                    'class Example {
    public function __construct(public ?Carbon $date = null) {}
}',
                    'class Example {
    // Laravel 12: Container now respects default parameter values when resolving dependencies. This constructor may behave differently.
    public function __construct(public ?Carbon $date = null) {}
}'
                ),
            ]
        );
    }
}
