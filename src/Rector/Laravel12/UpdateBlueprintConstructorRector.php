<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBlueprintConstructorRector extends AbstractRector
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

        $newExpr = $this->extractNewExpression($node);

        if ($newExpr === null) {
            return null;
        }

        if (!$newExpr->class instanceof Name) {
            return null;
        }

        if (
            !$this->isName($newExpr->class, 'Blueprint') &&
            !$this->isName($newExpr->class, 'Illuminate\Database\Schema\Blueprint')
        ) {
            return null;
        }

        if (count($newExpr->args) >= 4) {
            return null;
        }

        $existingComments = $node->getComments();
        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 12:')) {
                return null;
            }
        }

        $newComment = new Comment(
            '// Laravel 12: Blueprint constructor now requires $connection as the first parameter.'
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function extractNewExpression(Expression $node): ?New_
    {
        if ($node->expr instanceof New_) {
            return $node->expr;
        }

        if (
            $node->expr instanceof Node\Expr\Assign &&
            $node->expr->expr instanceof New_
        ) {
            return $node->expr->expr;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for Blueprint constructor signature change in Laravel 12',
            [
                new CodeSample(
                    '$blueprint = new Blueprint($table, $callback, $prefix);',
                    '// Laravel 12: Blueprint constructor now requires $connection as the first parameter.
$blueprint = new Blueprint($table, $callback, $prefix);',
                ),
            ],
        );
    }
}
