<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateColumnModificationRector extends AbstractRector
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

        // Check if this is a ->change() method call
        if ($methodName === "change") {
            // Add documentation comment about column modifier requirements
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: When modifying columns, you must explicitly include ALL modifiers " .
                        "you want to keep (unsigned, default, comment, etc.). Missing attributes will be dropped. */",
                ),
            ]);

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation comments for column modification behavior changes in Laravel 11",
            [
                new CodeSample(
                    '$table->integer(\'votes\')->nullable()->change()',
                    '/** Laravel 11: When modifying columns, you must explicitly include ALL modifiers you want to keep (unsigned, default, comment, etc.). Missing attributes will be dropped. */
$table->integer(\'votes\')->nullable()->change()',
                ),
            ],
        );
    }
}
