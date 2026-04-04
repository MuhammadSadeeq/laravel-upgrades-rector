<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PhpParser\Comment\Doc;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateStorageConfigRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 12: If no "local" disk is explicitly defined';

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Array_) {
            return null;
        }

        $disksItem = $this->findDisksItem($node);

        if (! $disksItem instanceof ArrayItem) {
            return null;
        }

        if (! $disksItem->value instanceof Array_) {
            return null;
        }

        if ($this->hasExplicitLocalDisk($disksItem->value) || $this->hasUpgradeComment($disksItem)) {
            return null;
        }

        $disksItem->setAttribute('comments', array_merge([
            new Doc('/** ' . self::COMMENT_MARKER . ', Laravel now defaults it to storage/app/private. Define disks.local.root explicitly to preserve storage/app. */'),
        ], $disksItem->getComments()));

        return $node;
    }

    private function findDisksItem(Array_ $array): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === 'disks') {
                return $item;
            }
        }

        return null;
    }

    private function hasExplicitLocalDisk(Array_ $disksArray): bool
    {
        foreach ($disksArray->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === 'local') {
                return true;
            }
        }

        return false;
    }

    private function hasUpgradeComment(ArrayItem $disksItem): bool
    {
        foreach ($disksItem->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add an advisory comment when the filesystems configuration relies on Laravel 12 local-disk defaults',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return [
    'disks' => [
        's3' => [
            'driver' => 's3',
        ],
    ],
];
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
return [
    /** Laravel 12: If no "local" disk is explicitly defined, Laravel now defaults it to storage/app/private. Define disks.local.root explicitly to preserve storage/app. */
    'disks' => [
        's3' => [
            'driver' => 's3',
        ],
    ],
];
CODE_SAMPLE
                ),
            ],
        );
    }
}
