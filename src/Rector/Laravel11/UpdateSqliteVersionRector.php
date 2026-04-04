<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSqliteVersionRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: SQLite 3.26.0 or greater is required';

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Array_) {
            return null;
        }

        $driverItem = $this->findArrayItem($node, 'driver');

        if (! $driverItem instanceof ArrayItem || ! $driverItem->value instanceof String_) {
            return null;
        }

        if ($driverItem->value->value !== 'sqlite') {
            return null;
        }

        foreach ($driverItem->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $driverItem->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. Verify the runtime SQLite version before upgrading.'),
        ], $driverItem->getComments()));
        $driverItem->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function findArrayItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === $key) {
                return $item;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn when Laravel 11 configuration still targets an SQLite connection',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return [
    'driver' => 'sqlite',
];
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
return [
    // Laravel 11: SQLite 3.26.0 or greater is required. Verify the runtime SQLite version before upgrading.
    'driver' => 'sqlite',
];
CODE_SAMPLE
                ),
            ]
        );
    }
}
