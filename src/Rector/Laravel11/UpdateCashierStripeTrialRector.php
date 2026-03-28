<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeTrialRector extends AbstractRector
{
    /** @var array<int, string> */
    private array $subscriptionCancelMethods = [
        "cancel",
        "cancelNow",
        "cancelAt",
    ];

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        $methodName = $this->getName($node->name);

        // Handle subscription cancellation methods
        if (in_array($methodName, $this->subscriptionCancelMethods, true)) {
            // Check if this is being called on what appears to be a subscription
            if ($this->isSubscriptionMethod($node)) {
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Cashier Stripe 15: {$methodName}() now always ends subscription trials. " .
                            "Any lingering trial will be ended when subscription is canceled. */",
                    ),
                ]);
                return $node;
            }
        }

        return null;
    }

    private function isSubscriptionMethod(MethodCall $methodCall): bool
    {
        // Try to determine if this method is being called on a subscription object
        if ($methodCall->var instanceof \PhpParser\Node\Expr\Variable) {
            $varName = $methodCall->var->name;
            if (is_string($varName)) {
                return str_contains($varName, "subscription") ||
                    str_contains($varName, "sub") ||
                    $varName === "billable";
            }
        }

        // Check for method chains that might indicate subscription context
        if ($methodCall->var instanceof MethodCall) {
            $parentMethodName = $this->getName($methodCall->var->name);
            if (
                in_array(
                    $parentMethodName,
                    ["subscription", "newSubscription", "subscriptions"],
                    true,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for Cashier Stripe 15.0 trial ending behavior when canceling subscriptions",
            [
                new CodeSample(
                    '$subscription->cancel()',
                    '/** Cashier Stripe 15: cancel() now always ends subscription trials. Any lingering trial will be ended when subscription is canceled. */
$subscription->cancel()',
                ),
            ],
        );
    }
}
