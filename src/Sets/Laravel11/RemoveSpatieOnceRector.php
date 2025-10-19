<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveSpatieOnceRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall) {
            return null;
        }

        // Check for spatie/once function calls
        if ($node->name instanceof Name) {
            $functionName = $this->getName($node->name);

            // If this is a call to spatie/once specific functions, add a comment
            if (
                str_contains($functionName, "spatie") ||
                str_contains($functionName, "Once")
            ) {
                $node->setAttribute("comments", [
                    new \PhpParser\Comment\Doc(
                        "/** Laravel 11: Remove spatie/once package dependency. " .
                            "Laravel now provides native once() function. */",
                    ),
                ]);
                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation about removing spatie/once package for Laravel 11",
            [
                new CodeSample(
                    'use Spatie\Once\Cache;

$result = once(function () {
    return expensive_operation();
});',
                    '/** Laravel 11: Remove spatie/once package dependency. Laravel now provides native once() function. */
$result = once(function () {
    return expensive_operation();
});',
                ),
            ],
        );
    }
}
