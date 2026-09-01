<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Reflection\ClassReflection;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeRector extends AbstractRector
{
    /** @var array<int, string> */
    private array $paymentMethodMethods = [
        'hasPaymentMethod',
        'paymentMethods',
        'deletePaymentMethods',
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
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

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update Cashier Stripe code for version 15.0 compatibility',
            [
                new CodeSample(
                    '$billable->hasPaymentMethod();',
                    "\$billable->hasPaymentMethod('card');",
                ),
            ]
        );
    }
}
