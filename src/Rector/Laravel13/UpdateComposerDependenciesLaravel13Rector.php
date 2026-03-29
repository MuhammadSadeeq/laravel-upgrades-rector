<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesLaravel13Rector extends AbstractRector
{
    /** @var array<string, string> */
    private const DEPENDENCY_UPDATES = [
        'laravel/framework' => '^13.0',
        'laravel/boost' => '^2.0',
        'laravel/tinker' => '^3.0',
        'phpunit/phpunit' => '^12.0',
        'pestphp/pest' => '^4.0',
    ];

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Array_) {
            return null;
        }

        $changed = false;

        foreach ($node->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if (! $item->key instanceof String_ || ! $item->value instanceof String_) {
                continue;
            }

            $packageName = $item->key->value;

            if (! isset(self::DEPENDENCY_UPDATES[$packageName])) {
                continue;
            }

            $targetVersion = self::DEPENDENCY_UPDATES[$packageName];

            if ($item->value->value === $targetVersion) {
                continue;
            }

            $item->value = new String_($targetVersion);
            $changed = true;
        }

        if (! $changed) {
            return null;
        }

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update dependency version strings for Laravel 13 compatibility',
            [
                new CodeSample(
                    '"laravel/framework" => "^12.0"',
                    '"laravel/framework" => "^13.0"',
                ),
            ]
        );
    }
}
