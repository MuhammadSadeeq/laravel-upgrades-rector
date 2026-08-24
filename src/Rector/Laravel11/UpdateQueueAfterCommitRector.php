<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateQueueAfterCommitRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: sync queue jobs now respect after_commit';

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Array_) {
            return null;
        }

        $driver = $this->findStringValue($node, 'driver');
        $afterCommitItem = $this->findArrayItem($node, 'after_commit');

        if ($driver !== 'sync') {
            return null;
        }

        if (! $afterCommitItem instanceof ArrayItem) {
            return null;
        }

        if (! $afterCommitItem->value instanceof ConstFetch || ! $this->isName($afterCommitItem->value->name, 'true')) {
            return null;
        }

        foreach ($afterCommitItem->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $afterCommitItem->setAttribute('comments', array_merge([
            new Comment('// '.self::COMMENT_MARKER.'. Review any sync jobs dispatched inside database transactions.'),
        ], $afterCommitItem->getComments()));

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

    private function findStringValue(Array_ $array, string $key): ?string
    {
        $item = $this->findArrayItem($array, $key);

        if (! $item instanceof ArrayItem || ! $item->value instanceof String_) {
            return null;
        }

        return $item->value->value;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn when the sync queue connection enables after_commit under Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return [
    'driver' => 'sync',
    'after_commit' => true,
];
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
return [
    'driver' => 'sync',
    // Laravel 11: sync queue jobs now respect after_commit. Review any sync jobs dispatched inside database transactions.
    'after_commit' => true,
];
CODE_SAMPLE
                ),
            ]
        );
    }
}
