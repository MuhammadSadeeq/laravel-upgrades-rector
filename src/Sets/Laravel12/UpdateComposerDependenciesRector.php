<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateComposerDependenciesRector extends AbstractRector
{
    /** @var array<string, string> */
    private array $dependencyUpdates = [
        'laravel/framework' => '^12.0',
        'phpunit/phpunit' => '^11.0',
        'pestphp/pest' => '^3.0',
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

        foreach ($node->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_ || !$item->value instanceof String_) {
                continue;
            }

            $packageName = $item->key->value;

            if (isset($this->dependencyUpdates[$packageName])) {
                $newVersion = $this->dependencyUpdates[$packageName];

                // Only update if the version is different
                if ($item->value->value !== $newVersion) {
                    $item->value = new String_($newVersion);
                    $hasUpdates = true;
                }
            }
        }

        return $hasUpdates ? $node : null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update composer.json dependencies for Laravel 12 compatibility',
            [
                new CodeSample(
                    '"laravel/framework": "^11.31"',
                    '"laravel/framework": "^12.0"'
                ),
                new CodeSample(
                    '"phpunit/phpunit": "^10.0"',
                    '"phpunit/phpunit": "^11.0"'
                ),
            ]
        );
    }
}