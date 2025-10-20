<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSchemaMethodsRector extends AbstractRector
{
    private array $schemaMethodsWithSchemaParam = [
        "getTables",
        "getViews",
        "getTypes",
        "getTableListing",
    ];

    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        // Check if this is a Schema method call
        if (!$this->isName($node->class, "Schema")) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if (!in_array($methodName, $this->schemaMethodsWithSchemaParam, true)) {
            return null;
        }

        // If no arguments are provided, this method will now return multi-schema results
        // Add a comment to indicate the behavior change
        if (count($node->args) === 0) {
            // Add a schema parameter to maintain single-schema behavior if needed
            // For now, we'll just add a comment indicating the change
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 12: This method now returns results from all schemas by default. " .
                        "Pass a schema name as parameter to limit results to a specific schema. */",
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation comments for Schema methods that now return multi-schema results by default in Laravel 12",
            [
                new CodeSample(
                    "Schema::getTables()",
                    '/** Laravel 12: This method now returns results from all schemas by default. Pass a schema name as parameter to limit results to a specific schema. */
Schema::getTables()',
                ),
            ],
        );
    }
}
