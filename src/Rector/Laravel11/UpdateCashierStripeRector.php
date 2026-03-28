<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Cashier Stripe 15:';

    /** @var array<int, string> */
    private array $paymentMethodMethods = [
        'hasPaymentMethod',
        'paymentMethods',
        'deletePaymentMethods',
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class, Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
        }

        if ($node instanceof Expression && $node->expr instanceof StaticCall) {
            return $this->refactorStaticCall($node, $node->expr);
        }

        return null;
    }

    private function refactorMethodCall(MethodCall $node): ?Node
    {
        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        if (! in_array($methodName, $this->paymentMethodMethods, true)) {
            return null;
        }

        if (count($node->args) !== 0) {
            return null;
        }

        $node->args[] = new Arg(new String_('card'));

        return $node;
    }

    private function refactorStaticCall(Expression $stmt, StaticCall $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        if (! $this->isName($node->class, 'Laravel\\Cashier\\Cashier')) {
            return null;
        }

        $existingComments = $stmt->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// ' . self::COMMENT_MARKER . ' Review Cashier static configuration for v15 compatibility'
        );
        $stmt->setAttribute('comments', array_merge([$newComment], $existingComments));
        $stmt->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $stmt;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update Cashier Stripe code for version 15.0 compatibility',
            [
                new CodeSample(
                    '$billable->hasPaymentMethod()',
                    "\$billable->hasPaymentMethod('card')",
                ),
            ]
        );
    }
}
