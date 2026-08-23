<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCacheConfigurationRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Return_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Return_ || ! $node->expr instanceof Array_) {
            return null;
        }

        $commentsByItem = [];
        $filePath = $this->file->getFilePath();

        if ($this->matchesConfigFile($filePath, 'cache')) {
            $firstItem = $this->findFirstArrayItem($node->expr);

            if ($firstItem instanceof ArrayItem) {
                $commentsByItem[spl_object_id($firstItem)] = [
                    'item' => $firstItem,
                    'comments' => [
                        'Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.',
                        'Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.',
                    ],
                ];
            }
        }

        $redisItem = $this->findArrayItem($node->expr, 'redis');
        if ($redisItem instanceof ArrayItem && $this->hasRedisConfigurationWithoutExplicitPrefix($node->expr)) {
            $commentsByItem[spl_object_id($redisItem)] = [
                'item' => $redisItem,
                'comments' => [
                    'Laravel 13: default Redis prefixes now use hyphenated suffixes. Set REDIS_PREFIX explicitly if you need the previous generated value.',
                ],
            ];
        }

        if ($this->matchesConfigFile($filePath, 'session') && ! $this->hasStringKey($node->expr, 'cookie')) {
            $firstItem = $this->findFirstArrayItem($node->expr);

            if ($firstItem instanceof ArrayItem) {
                $existingEntry = $commentsByItem[spl_object_id($firstItem)] ?? ['item' => $firstItem, 'comments' => []];
                $existingEntry['comments'][] = 'Laravel 13: default session cookie names now use Str::snake(APP_NAME). Set SESSION_COOKIE explicitly if you need the previous generated value.';
                $commentsByItem[spl_object_id($firstItem)] = $existingEntry;
            }
        }

        if ($commentsByItem === []) {
            return null;
        }

        $hasChanges = false;

        foreach ($commentsByItem as $entry) {
            /** @var ArrayItem $item */
            $item = $entry['item'];
            $newComments = [];

            foreach ($entry['comments'] as $commentText) {
                if ($this->hasComment($item, $commentText)) {
                    continue;
                }

                $newComments[] = new Comment('// ' . $commentText);
            }

            if ($newComments === []) {
                continue;
            }

            $item->setAttribute('comments', array_merge($newComments, $item->getComments()));
            $hasChanges = true;
        }

        if (! $hasChanges) {
            return null;
        }

        return $node;
    }

    private function hasNestedRedisPrefix(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $this->getArrayKeyName($item) !== 'redis' || ! $item->value instanceof Array_) {
                continue;
            }

            foreach ($item->value->items as $redisItem) {
                if (! $redisItem instanceof ArrayItem || $this->getArrayKeyName($redisItem) !== 'options' || ! $redisItem->value instanceof Array_) {
                    continue;
                }

                foreach ($redisItem->value->items as $optionItem) {
                    if ($optionItem instanceof ArrayItem && $this->getArrayKeyName($optionItem) === 'prefix') {
                        return true;
                    }
                }

                return false;
            }
        }

        return false;
    }

    private function hasRedisConfigurationWithoutExplicitPrefix(Array_ $array): bool
    {
        return $this->hasStringKey($array, 'redis') && ! $this->hasNestedRedisPrefix($array);
    }

    private function hasStringKey(Array_ $array, string $key): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $this->getArrayKeyName($item) !== $key) {
                continue;
            }

            return true;
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

    private function hasComment(ArrayItem $item, string $commentText): bool
    {
        foreach ($item->getComments() as $comment) {
            if (str_contains($comment->getText(), $commentText)) {
                return true;
            }
        }

        return false;
    }

    private function findArrayItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $this->getArrayKeyName($item) !== $key) {
                continue;
            }

            return $item;
        }

        return null;
    }

    private function findFirstArrayItem(Array_ $array): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem) {
                return $item;
            }
        }

        return null;
    }

    private function matchesConfigFile(string $filePath, string $configName): bool
    {
        if (str_ends_with($filePath, DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $configName . '.php')) {
            return true;
        }

        if (! str_contains($filePath, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
            return false;
        }

        $fixtureBaseName = basename($filePath);

        return $fixtureBaseName === $configName . '.php.inc'
            || $fixtureBaseName === $configName . '.php'
            || str_starts_with($fixtureBaseName, $configName . '_');
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comments for Laravel 13 cache configuration default changes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return [
    'default' => env('CACHE_STORE', 'database'),
];
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
return [
    // Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.
    // Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.
    'default' => env('CACHE_STORE', 'database'),
];
CODE_SAMPLE,
                ),
            ],
        );
    }
}
