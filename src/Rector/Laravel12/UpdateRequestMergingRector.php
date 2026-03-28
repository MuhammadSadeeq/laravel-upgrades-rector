<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRequestMergingRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Expression) {
            return null;
        }

        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        if (!$this->isName($node->expr->name, 'mergeIfMissing')) {
            return null;
        }

        if (!$this->isObjectType($node->expr->var, new ObjectType('Illuminate\\Http\\Request'))) {
            return null;
        }

        $existingComments = $node->getComments();
        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 12:')) {
                return null;
            }
        }

        $newComment = new Comment(
            '// Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.'
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for Request mergeIfMissing() nested array merging behavior change in Laravel 12',
            [
                new CodeSample(
                    '$request->mergeIfMissing($data);',
                    '// Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
$request->mergeIfMissing($data);'
                ),
            ]
        );
    }
}
