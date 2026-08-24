<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeMigrationRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Cashier Stripe 15:';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof MethodCall) {
            return null;
        }

        if (! $this->isBlueprintContext($node->expr)) {
            return null;
        }

        $methodName = $this->getName($node->expr->name);

        if ($methodName === 'renameColumn') {
            return $this->handleRenameColumn($node, $node->expr);
        }

        if ($methodName === 'dropUnique') {
            return $this->handleDropUnique($node, $node->expr);
        }

        return null;
    }

    private function isBlueprintContext(MethodCall $node): bool
    {
        return $this->isObjectType($node->var, new ObjectType('Illuminate\\Database\\Schema\\Blueprint'));
    }

    private function handleRenameColumn(Expression $stmt, MethodCall $node): ?Node
    {
        if (count($node->args) < 2) {
            return null;
        }

        $fromArg = $node->args[0];
        $toArg = $node->args[1];

        if (! $fromArg instanceof Arg || ! $toArg instanceof Arg) {
            return null;
        }

        if (! $fromArg->value instanceof String_ || ! $toArg->value instanceof String_) {
            return null;
        }

        if ($fromArg->value->value !== 'name' || $toArg->value->value !== 'type') {
            return null;
        }

        $existingComments = $stmt->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// '.self::COMMENT_MARKER.' Renamed "name" column to "type" in subscriptions table'
        );
        $stmt->setAttribute('comments', array_merge([$newComment], $existingComments));

        return $stmt;
    }

    private function handleDropUnique(Expression $stmt, MethodCall $node): ?Node
    {
        if (count($node->args) < 1) {
            return null;
        }

        $arg = $node->args[0];

        if (! $arg instanceof Arg || ! $arg->value instanceof Array_) {
            return null;
        }

        $values = [];

        foreach ($arg->value->items as $item) {
            if ($item !== null && $item->value instanceof String_) {
                $values[] = $item->value->value;
            }
        }

        if (! in_array('subscription_id', $values, true) || ! in_array('stripe_price', $values, true)) {
            return null;
        }

        $existingComments = $stmt->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// '.self::COMMENT_MARKER.' Unique constraint replaced with regular index on subscription_items'
        );
        $stmt->setAttribute('comments', array_merge([$newComment], $existingComments));

        return $stmt;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add documentation for Cashier Stripe 15.0 database migration changes',
            [
                new CodeSample(
                    "\$table->renameColumn('name', 'type');",
                    <<<'CODE_SAMPLE'
// Cashier Stripe 15: Renamed "name" column to "type" in subscriptions table
$table->renameColumn('name', 'type');
CODE_SAMPLE
                ),
            ]
        );
    }
}
