<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveSpatieOnceRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Use_::class];
    }

    /**
     * @return int|null
     */
    public function refactor(Node $node)
    {
        if (!$node instanceof Use_) {
            return null;
        }

        // Check for Spatie\Once namespace imports
        foreach ($node->uses as $use) {
            $name = $use->name->toString();

            // If importing from Spatie\Once namespace, remove the entire use statement
            // Laravel 11 has native once() function with identical signature
            if (str_starts_with($name, 'Spatie\\Once\\') || $name === 'Spatie\\Once') {
                // Remove this use statement
                return NodeVisitor::REMOVE_NODE;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Remove Spatie\\Once use statements for Laravel 11 (Laravel now provides native once() function)",
            [
                new CodeSample(
                    'use Spatie\Once\Cache;

$result = once(function () {
    return expensive_operation();
});',
                    '$result = once(function () {
    return expensive_operation();
});',
                ),
            ],
        );
    }
}
