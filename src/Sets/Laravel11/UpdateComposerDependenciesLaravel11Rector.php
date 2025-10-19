<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesLaravel11Rector extends AbstractRector
{
    private array $dependencyUpdates = [
        'laravel/framework' => '^11.0',
        'nunomaduro/collision' => '^8.1',
        'laravel/breeze' => '^2.0',
        'laravel/cashier' => '^15.0',
        'laravel/dusk' => '^8.0',
        'laravel/jetstream' => '^5.0',
        'laravel/octane' => '^2.3',
        'laravel/passport' => '^12.0',
        'laravel/sanctum' => '^4.0',
        'laravel/scout' => '^10.0',
        'laravel/spark-stripe' => '^5.0',
        'laravel/telescope' => '^5.0',
        'livewire/livewire' => '^3.4',
        'inertiajs/inertia-laravel' => '^1.0',
    ];

    private array $packagesToRemove = [
        'doctrine/dbal',
        'spatie/once',
    ];

    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Array_) {
            return null;
        }

        $hasUpdates = false;
        $itemsToRemove = [];

        foreach ($node->items as $key => $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_ || !$item->value instanceof String_) {
                continue;
            }

            $packageName = $item->key->value;

            // Check if this package should be removed
            if (in_array($packageName, $this->packagesToRemove, true)) {
                $itemsToRemove[] = $key;
                $hasUpdates = true;
                continue;
            }

            // Check if this package should be updated
            if (isset($this->dependencyUpdates[$packageName])) {
                $newVersion = $this->dependencyUpdates[$packageName];

                // Only update if the version is different
                if ($item->value->value !== $newVersion) {
                    $item->value = new String_($newVersion);
                    $hasUpdates = true;
                }
            }
        }

        // Remove packages that should be removed (in reverse order to preserve indices)
        foreach (array_reverse($itemsToRemove) as $indexToRemove) {
            unset($node->items[$indexToRemove]);
        }

        // Re-index array items
        if (!empty($itemsToRemove)) {
            $node->items = array_values($node->items);
        }

        return $hasUpdates ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update composer.json dependencies for Laravel 11 compatibility',
            [
                new CodeSample(
                    '"laravel/framework" => "^10.0"',
                    '"laravel/framework" => "^11.0"'
                ),
                new CodeSample(
                    '"doctrine/dbal" => "^3.0"',
                    '// Package removed - no longer needed in Laravel 11'
                ),
            ]
        );
    }
}
