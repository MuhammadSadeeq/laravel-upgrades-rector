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

        $commentsToAdd = [];
        $filePath = $this->file->getFilePath();

        if ($this->matchesConfigFile($filePath, 'cache')) {
            if (! $this->hasStringKey($node->expr, 'serializable_classes')) {
                $commentsToAdd[] = 'Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.';
            }

            if (! $this->hasStringKey($node->expr, 'prefix')) {
                $commentsToAdd[] = 'Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.';
            }
        }

        if ($this->matchesConfigFile($filePath, 'database')) {
            if ($this->hasStringKey($node->expr, 'redis') && ! $this->hasNestedRedisPrefix($node->expr)) {
                $commentsToAdd[] = 'Laravel 13: default Redis prefixes now use hyphenated suffixes. Set REDIS_PREFIX explicitly if you need the previous generated value.';
            }
        }

        if ($this->matchesConfigFile($filePath, 'session') && ! $this->hasStringKey($node->expr, 'cookie')) {
            $commentsToAdd[] = 'Laravel 13: default session cookie names now use Str::snake(APP_NAME). Set SESSION_COOKIE explicitly if you need the previous generated value.';
        }

        if ($commentsToAdd === []) {
            return null;
        }

        $newComments = [];

        foreach ($commentsToAdd as $commentText) {
            if ($this->hasComment($node, $commentText)) {
                continue;
            }

            $newComments[] = new Comment('// ' . $commentText);
        }

        if ($newComments === []) {
            return null;
        }

        $node->setAttribute('comments', array_merge($newComments, $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function hasStringKey(Array_ $array, string $key): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === $key) {
                return true;
            }
        }

        return false;
    }

    private function hasNestedRedisPrefix(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_ || $item->key->value !== 'redis' || ! $item->value instanceof Array_) {
                continue;
            }

            foreach ($item->value->items as $redisItem) {
                if (! $redisItem instanceof ArrayItem || ! $redisItem->key instanceof String_ || $redisItem->key->value !== 'options' || ! $redisItem->value instanceof Array_) {
                    continue;
                }

                return $this->hasStringKey($redisItem->value, 'prefix');
            }
        }

        return false;
    }

    private function hasComment(Return_ $return, string $commentText): bool
    {
        foreach ($return->getComments() as $comment) {
            if (str_contains($comment->getText(), $commentText)) {
                return true;
            }
        }

        return false;
    }

    private function matchesConfigFile(string $filePath, string $configName): bool
    {
        if (str_ends_with($filePath, DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $configName . '.php')) {
            return true;
        }

        if (! str_contains($filePath, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
            return false;
        }

        $fixtureBaseName = basename($filePath, '.inc');

        return $fixtureBaseName === $configName . '.php' || str_starts_with($fixtureBaseName, $configName . '_');
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
// Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.
// Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.
return [
    'default' => env('CACHE_STORE', 'database'),
];
CODE_SAMPLE,
                ),
            ],
        );
    }
}
