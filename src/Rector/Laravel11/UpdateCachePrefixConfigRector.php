<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCachePrefixConfigRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: Redis, Memcached, and DynamoDB cache prefixes no longer receive an automatic ":" suffix';

    /** @var array<int, string> */
    private array $supportedDrivers = ['redis', 'memcached', 'dynamodb'];

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
        $prefixItem = $this->findArrayItem($node, 'prefix');

        if ($driver === null || ! in_array($driver, $this->supportedDrivers, true)) {
            return null;
        }

        if (! $prefixItem instanceof ArrayItem) {
            return null;
        }

        foreach ($prefixItem->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $prefixItem->setAttribute('comments', array_merge([
            new Comment('// '.self::COMMENT_MARKER.'. Add it manually if you need the previous behavior.'),
        ], $prefixItem->getComments()));

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
            'Warn on cache stores whose prefixes relied on Laravel adding a trailing colon automatically',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return [
    'driver' => 'redis',
    'prefix' => 'my-app',
];
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
return [
    'driver' => 'redis',
    // Laravel 11: Redis, Memcached, and DynamoDB cache prefixes no longer receive an automatic ":" suffix. Add it manually if you need the previous behavior.
    'prefix' => 'my-app',
];
CODE_SAMPLE
                ),
            ]
        );
    }
}
