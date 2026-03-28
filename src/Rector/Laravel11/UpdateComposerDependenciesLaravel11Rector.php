<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesLaravel11Rector extends AbstractRector
{
    /** @var array<string, string> */
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

            if (! isset($this->dependencyUpdates[$packageName])) {
                continue;
            }

            $targetVersion = $this->dependencyUpdates[$packageName];

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
            'Update dependency version strings for Laravel 11 compatibility',
            [
                new CodeSample(
                    '"laravel/framework" => "^10.0"',
                    '"laravel/framework" => "^11.0"',
                ),
            ]
        );
    }
}
