<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBlueprintConstructorRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class, Return_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Expression && !$node instanceof Return_) {
            return null;
        }

        if (! $this->containsOutdatedBlueprintConstructor($node)) {
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

    private function containsOutdatedBlueprintConstructor(Expression|Return_ $node): bool
    {
        $newExpr = $node instanceof Expression ? $this->extractNewExpression($node) : $this->extractReturnNewExpression($node);

        if ($newExpr instanceof New_ && $this->isOutdatedBlueprintConstructor($newExpr)) {
            return true;
        }

        $found = false;
        $expr = $node->expr;

        if (! $expr instanceof Node) {
            return false;
        }

        $this->traverseNodesWithCallable($expr, function (Node $subNode) use (&$found): ?int {
            if (! $subNode instanceof New_) {
                return null;
            }

            if (! $this->isOutdatedBlueprintConstructor($subNode)) {
                return null;
            }

            $found = true;

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        });

        return $found;
    }

    private function isOutdatedBlueprintConstructor(New_ $newExpr): bool
    {
        if (!$newExpr->class instanceof Name) {
            return false;
        }

        if (
            !$this->isName($newExpr->class, 'Blueprint') &&
            !$this->isName($newExpr->class, 'Illuminate\Database\Schema\Blueprint')
        ) {
            return false;
        }

        return count($newExpr->args) < 4;
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

    private function extractReturnNewExpression(Return_ $node): ?New_
    {
        return $node->expr instanceof New_ ? $node->expr : null;
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
