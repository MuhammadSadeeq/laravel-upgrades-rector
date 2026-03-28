<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Arg;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCashierStripeMigrationRector extends AbstractRector
{
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

        // Handle renameColumn for subscriptions table 'name' to 'type'
        if ($methodName === "renameColumn") {
            if (count($node->args) >= 2) {
                $fromArg = $node->args[0];
                $toArg = $node->args[1];

                if (
                    $fromArg instanceof Arg &&
                    $toArg instanceof Arg &&
                    $fromArg->value instanceof String_ &&
                    $toArg->value instanceof String_ &&
                    $fromArg->value->value === "name" &&
                    $toArg->value->value === "type"
                ) {
                    // Add comment about Cashier Stripe migration
                    $node->setAttribute("comments", [
                        new \PhpParser\Comment\Doc(
                            '/** Cashier Stripe 15: Renamed "name" column to "type" in subscriptions table ' .
                                "to better indicate subscription type rather than customer-facing name. */",
                        ),
                    ]);
                    return $node;
                }
            }
        }

        // Handle dropUnique and index for subscription_items table
        if ($methodName === "dropUnique") {
            if (count($node->args) >= 1) {
                $arg = $node->args[0];
                if ($arg instanceof Arg && $arg->value instanceof \PhpParser\Node\Expr\Array_) {
                    $array = $arg->value;
                    $hasSubscriptionId = false;
                    $hasStripePrice = false;

                    foreach ($array->items as $item) {
                        if ($item !== null && $item->value instanceof String_) {
                            if ($item->value->value === "subscription_id") {
                                $hasSubscriptionId = true;
                            }
                            if ($item->value->value === "stripe_price") {
                                $hasStripePrice = true;
                            }
                        }
                    }

                    if ($hasSubscriptionId && $hasStripePrice) {
                        $node->setAttribute("comments", [
                            new \PhpParser\Comment\Doc(
                                "/** Cashier Stripe 15: Converting unique constraint to regular index " .
                                    'on subscription_items table. Follow with ->index([\'subscription_id\', \'stripe_price\']) */',
                            ),
                        ]);
                        return $node;
                    }
                }
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for Cashier Stripe 15.0 database migration changes",
            [
                new CodeSample(
                    '$table->renameColumn(\'name\', \'type\')',
                    '/** Cashier Stripe 15: Renamed "name" column to "type" in subscriptions table to better indicate subscription type rather than customer-facing name. */
$table->renameColumn(\'name\', \'type\')',
                ),
                new CodeSample(
                    '$table->dropUnique([\'subscription_id\', \'stripe_price\'])',
                    '/** Cashier Stripe 15: Converting unique constraint to regular index on subscription_items table. Follow with ->index([\'subscription_id\', \'stripe_price\']) */
$table->dropUnique([\'subscription_id\', \'stripe_price\'])',
                ),
            ],
        );
    }
}
