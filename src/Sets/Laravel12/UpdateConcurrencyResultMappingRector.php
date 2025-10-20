<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\StaticCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateConcurrencyResultMappingRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        // Check if this is Concurrency::run call
        if (
            !$this->isName($node->class, "Concurrency") ||
            !$this->isName($node->name, "run")
        ) {
            return null;
        }

        // Check if the argument is an array
        if (
            !isset($node->args[0]) ||
            !$node->args[0] instanceof \PhpParser\Node\Arg ||
            !$node->args[0]->value instanceof Array_
        ) {
            return null;
        }

        $array = $node->args[0]->value;
        $hasAssociativeKeys = false;

        // Check if any items have keys (associative array)
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->key !== null) {
                $hasAssociativeKeys = true;
                break;
            }
        }

        // Only add a comment if using associative arrays (where behavior changed)
        if ($hasAssociativeKeys) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 12: Concurrency::run() now preserves associative array keys in results. " .
                        "Results will be returned with their original keys instead of numeric indices. */",
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for Concurrency::run() result index mapping behavior change in Laravel 12",
            [
                new CodeSample(
                    '$result = Concurrency::run([
    \'task-1\' => fn () => 1 + 1,
    \'task-2\' => fn () => 2 + 2,
]);',
                    '/** Laravel 12: Concurrency::run() now preserves associative array keys in results. Results will be returned with their original keys instead of numeric indices. */
$result = Concurrency::run([
    \'task-1\' => fn () => 1 + 1,
    \'task-2\' => fn () => 2 + 2,
]);',
                ),
            ],
        );
    }
}
