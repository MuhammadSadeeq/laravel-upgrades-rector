<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateStorageConfigRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 12: If no "local" disk is explicitly defined';

    public function getNodeTypes(): array
    {
        return [Return_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Return_ || ! $node->expr instanceof Array_) {
            return null;
        }

        if (! $this->containsDisksConfiguration($node->expr)) {
            return null;
        }

        $disksItem = $this->findDisksItem($node->expr);

        if (! $disksItem instanceof ArrayItem) {
            return null;
        }

        if ($this->hasExplicitLocalDisk($node->expr) || $this->hasUpgradeComment($disksItem)) {
            return null;
        }

        $comment = new Doc('/** '.self::COMMENT_MARKER.', Laravel now defaults it to storage/app/private. Define disks.local.root explicitly to preserve storage/app. */');
        $disksItem->setAttribute('comments', array_merge([$comment], $disksItem->getComments()));

        return $node;
    }

    private function containsDisksConfiguration(Array_ $array): bool
    {
        return $this->findDisksItem($array) instanceof ArrayItem;
    }

    private function findDisksItem(Array_ $array): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $this->getArrayKeyName($item) !== 'disks' || ! $item->value instanceof Array_) {
                continue;
            }

            return $item;
        }

        return null;
    }

    private function hasExplicitLocalDisk(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($this->getArrayKeyName($item) !== 'disks' || ! $item->value instanceof Array_) {
                continue;
            }

            foreach ($item->value->items as $diskItem) {
                if ($diskItem instanceof ArrayItem && $this->getArrayKeyName($diskItem) === 'local') {
                    return true;
                }
            }
        }

        return false;
    }

    private function getArrayKeyName(ArrayItem $item): ?string
    {
        if (! $item->key instanceof String_) {
            return null;
        }

        return $item->key->value;
    }

    private function hasUpgradeComment(ArrayItem $item): bool
    {
        foreach ($item->getComments() as $comment) {
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
