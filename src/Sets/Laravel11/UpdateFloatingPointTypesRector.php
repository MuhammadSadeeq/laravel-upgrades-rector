<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\LNumber;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateFloatingPointTypesRector extends AbstractRector
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

        // Handle double() method - remove total and places arguments
        if ($methodName === "double") {
            if (count($node->args) > 1) {
                // Keep only the first argument (column name)
                $node->args = [$node->args[0]];
                return $node;
            }
        }

        // Handle float() method - remove total and places, add precision parameter if needed
        if ($methodName === "float") {
            if (count($node->args) > 1) {
                // Remove total and places arguments
                $node->args = [$node->args[0]];

                // Add comment about precision parameter
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11: float() method signature changed. " .
                            'Use precision parameter if needed: ->float(\'amount\', precision: 53) */',
                    ),
                ]);
                return $node;
            }
        }

        // Handle unsigned methods that have been removed
        $removedUnsignedMethods = [
            "unsignedDecimal",
            "unsignedDouble",
            "unsignedFloat",
        ];

        if (in_array($methodName, $removedUnsignedMethods, true)) {
            // Convert to base method and chain unsigned()
            $baseMethod = match ($methodName) {
                "unsignedDecimal" => "decimal",
                "unsignedDouble" => "double",
                "unsignedFloat" => "float",
            };

            // Change method name to base method
            $node->name = new Identifier($baseMethod);

            // For double and float, remove precision arguments (keep only column name)
            if (($baseMethod === "double" || $baseMethod === "float") && count($node->args) > 1) {
                $node->args = [$node->args[0]];
            }

            // Create a new method call that chains ->unsigned()
            $unsignedCall = new MethodCall($node, new Identifier('unsigned'));

            return $unsignedCall;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update floating-point column types for Laravel 11 compatibility",
            [
                new CodeSample(
                    '$table->double(\'amount\', 8, 2)',
                    '$table->double(\'amount\')',
                ),
                new CodeSample(
                    '$table->float(\'amount\', 8, 2)',
                    '$table->float(\'amount\')',
                ),
                new CodeSample(
                    '$table->unsignedDouble(\'amount\')',
                    '$table->double(\'amount\')->unsigned()',
                ),
            ],
        );
    }
}
