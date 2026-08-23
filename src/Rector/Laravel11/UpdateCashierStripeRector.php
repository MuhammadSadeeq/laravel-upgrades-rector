<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Reflection\ClassReflection;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade cashier-card-default';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

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

        if (! $this->isBillableReceiver($node)) {
            return null;
        }

        $node->args[] = new Arg(new String_('card'));

        return $node;
    }

    private function isBillableReceiver(MethodCall $node): bool
    {
        foreach ($this->getType($node->var)->getObjectClassReflections() as $classReflection) {
            if (! $classReflection instanceof ClassReflection) {
                continue;
            }

            if ($classReflection->hasTraitUse('Laravel\\Cashier\\Billable')) {
                return true;
            }
        }

        return false;
    }

    private function refactorStaticCall(Expression $stmt, StaticCall $node): ?Node
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        if (! $this->isName($node->class, 'Laravel\\Cashier\\Cashier')) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $stmt,
            self::COMMENT_MARKER,
            'Review Cashier static configuration for v15 compatibility'
        )) {
            return null;
        }

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
