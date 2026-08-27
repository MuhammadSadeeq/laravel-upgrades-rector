<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12 preserves associative keys returned by Concurrency::run().
 *
 * @implements Rule<StaticCall>
 */
final class ConcurrencyRunKeyedResultsRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof StaticCall
            || ! $node->class instanceof Node\Name
            || ! $node->name instanceof Identifier
            || $node->name->toLowerString() !== 'run'
            || ltrim($scope->resolveName($node->class), '\\') !== 'Illuminate\\Support\\Facades\\Concurrency'
            || ! isset($node->args[0])
            || ! $node->args[0] instanceof Node\Arg
            || ! $node->args[0]->value instanceof Array_) {
            return [];
        }

        foreach ($node->args[0]->value->items as $item) {
            if ($item instanceof ArrayItem && $item->key !== null) {
                return [
                    RuleErrorBuilder::message(
                        'Concurrency::run() now preserves associative keys in Laravel 12.'
                    )->identifier('laravelUpgrade.concurrencyRunKeyedResults')
                        ->tip('Update result handling if it assumes numeric indexes; associative keys are retained.')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
