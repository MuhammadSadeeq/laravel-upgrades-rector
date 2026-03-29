<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePaginationViewNamesRector extends AbstractRector
{
    /** @var array<string, string> */
    private const VIEW_MAP = [
        'pagination::default' => 'pagination::bootstrap-3',
        'pagination::simple-default' => 'pagination::simple-bootstrap-3',
    ];

    public function getNodeTypes(): array
    {
        return [String_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof String_) {
            return null;
        }

        if (!isset(self::VIEW_MAP[$node->value])) {
            return null;
        }

        $node->value = self::VIEW_MAP[$node->value];

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename deprecated pagination view names to their Bootstrap 3 equivalents in Laravel 13',
            [
                new CodeSample(
                    "\$paginator->links('pagination::default');",
                    "\$paginator->links('pagination::bootstrap-3');",
                ),
                new CodeSample(
                    "\$paginator->links('pagination::simple-default');",
                    "\$paginator->links('pagination::simple-bootstrap-3');",
                ),
            ]
        );
    }
}
