<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\MethodCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateTelescopeRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof StaticCall && !$node instanceof MethodCall) {
            return null;
        }

        // Handle Telescope static calls
        if (
            $node instanceof StaticCall &&
            $this->isName($node->class, "Telescope")
        ) {
            $methodName = $this->getName($node->name);

            // Document common Telescope methods that might be affected by migration changes
            if (
                in_array(
                    $methodName,
                    ["filter", "tag", "recordQueries", "recordRequests"],
                    true,
                )
            ) {
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11 Telescope 5: Migrations are no longer auto-loaded. " .
                            "Run: php artisan vendor:publish --tag=telescope-migrations */",
                    ),
                ]);
                return $node;
            }
        }

        // Handle Telescope method calls on instances
        if ($node instanceof MethodCall) {
            $methodName = $this->getName($node->name);

            // Check if this might be a Telescope-related method call
            if (
                $methodName === "telescope" ||
                $this->isTelescopeContext($node)
            ) {
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11 Telescope 5: Migrations are no longer auto-loaded. " .
                            "Run: php artisan vendor:publish --tag=telescope-migrations */",
                    ),
                ]);
                return $node;
            }
        }

        return null;
    }

    private function isTelescopeContext(MethodCall $methodCall): bool
    {
        // Check if the variable name suggests Telescope usage
        if ($methodCall->var instanceof \PhpParser\Node\Expr\Variable) {
            $varName = $methodCall->var->name;
            if (is_string($varName)) {
                return str_contains(strtolower($varName), "telescope");
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for Telescope 5.0 migration publishing requirement",
            [
                new CodeSample(
                    'Telescope::filter(function (IncomingEntry $entry) {
    return $entry->type === EntryType::REQUEST;
})',
                    '/** Laravel 11 Telescope 5: Migrations are no longer auto-loaded. Run: php artisan vendor:publish --tag=telescope-migrations */
Telescope::filter(function (IncomingEntry $entry) {
    return $entry->type === EntryType::REQUEST;
})',
                ),
            ],
        );
    }
}
