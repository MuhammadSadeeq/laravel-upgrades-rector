<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Arg;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeRector extends AbstractRector
{
    private array $paymentMethodMethods = [
        "hasPaymentMethod",
        "paymentMethods",
        "deletePaymentMethods",
    ];

    private array $removedMethods = [
        "isDeleted" => "Invoice",
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

        // Handle payment method related methods - add 'card' parameter for backward compatibility
        if (in_array($methodName, $this->paymentMethodMethods, true)) {
            // Only add parameter if no arguments are present
            if (count($node->args) === 0) {
                $node->args[] = new Arg(new String_("card"));

                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Cashier Stripe 15: {$methodName}() now fetches all payment method types. " .
                            "Added 'card' parameter to maintain previous behavior. */",
                    ),
                ]);

                return $node;
            }
        }

        // Handle removed methods
        if (isset($this->removedMethods[$methodName])) {
            $className = $this->removedMethods[$methodName];
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Cashier Stripe 15: {$methodName}() method removed from {$className}. " .
                        "The 'deleted' status on invoices no longer exists. */",
                ),
            ]);
            return $node;
        }

        // Handle newSubscriptionName method rename
        if ($methodName === "newSubscriptionName") {
            $node->name = new \PhpParser\Node\Identifier("newSubscriptionType");

            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Cashier Stripe 15: newSubscriptionName() renamed to newSubscriptionType() */",
                ),
            ]);

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update Cashier Stripe code for version 15.0 compatibility",
            [
                new CodeSample(
                    '$billable->hasPaymentMethod()',
                    "/** Cashier Stripe 15: hasPaymentMethod() now fetches all payment method types. Added 'card' parameter to maintain previous behavior. */
\$billable->hasPaymentMethod('card')",
                ),
                new CodeSample(
                    'public function newSubscriptionName($payload)',
                    '/** Cashier Stripe 15: newSubscriptionName() renamed to newSubscriptionType() */
public function newSubscriptionType($payload)',
                ),
            ],
        );
    }
}
