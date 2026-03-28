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
    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Array_) {
            return null;
        }

        // Look for filesystem disk configuration
        $isLocalDisk = false;
        $hasRoot = false;
        $rootItem = null;

        foreach ($node->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_) {
                continue;
            }

            // Check if this is a local disk configuration
            if (
                $item->key->value === 'driver' &&
                $item->value instanceof String_
            ) {
                if ($item->value->value === 'local') {
                    $isLocalDisk = true;
                }
            }

            // Check for root configuration
            if (
                $item->key->value === 'root' &&
                $item->value instanceof String_
            ) {
                $hasRoot = true;
                $rootItem = $item;
            }
        }

        // If this is a local disk with root configuration
        if ($isLocalDisk && $hasRoot && $rootItem !== null && $rootItem->value instanceof String_) {
            $currentRoot = $rootItem->value->value;

            // Idempotency: skip if root already ends with /private
            if (str_ends_with($currentRoot, '/private')) {
                return null;
            }

            // If root is set to storage/app, update to storage/app/private for Laravel 12
            if ($currentRoot === 'storage/app') {
                $rootItem->value = new String_('storage/app/private');
            } elseif (str_ends_with($currentRoot, '/storage/app')) {
                $rootItem->value = new String_(
                    substr($currentRoot, 0, -strlen('/storage/app')) . '/storage/app/private',
                );
            } else {
                return null;
            }

            // Add comment about the change
            $rootItem->setAttribute('comments', [
                new Doc(
                    '/** Laravel 12: Local filesystem disk now defaults to storage/app/private */',
                ),
            ]);

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update local filesystem disk root path to storage/app/private for Laravel 12 compatibility",
            [
                new CodeSample(
                    "'root' => 'storage/app'",
                    "'root' => 'storage/app/private'",
                ),
            ],
        );
    }
}
