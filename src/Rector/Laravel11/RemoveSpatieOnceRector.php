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
     * @return int|Node|null
     */
    public function refactor(Node $node)
    {
        if (! $node instanceof Use_) {
            return null;
        }

        $spatieUseItems = [];
        $otherUseItems = [];

        foreach ($node->uses as $use) {
            $name = $use->name->toString();

            if (str_starts_with($name, 'Spatie\\Once\\') || $name === 'Spatie\\Once') {
                $spatieUseItems[] = $use;
            } else {
                $otherUseItems[] = $use;
            }
        }

        if ($spatieUseItems === []) {
            return null;
        }

        if ($otherUseItems === []) {
            return NodeVisitor::REMOVE_NODE;
        }

        $node->uses = $otherUseItems;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Spatie\Once use statements (Laravel 11 provides native once() function)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Spatie\Once\Cache;

$result = once(function () {
    return expensive_operation();
});
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
$result = once(function () {
    return expensive_operation();
});
CODE_SAMPLE
                ),
            ]
        );
    }
}
