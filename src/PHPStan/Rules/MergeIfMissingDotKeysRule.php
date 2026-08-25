<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12: mergeIfMissing() now supports nested array merging with dot
 * notation. This may change behaviour for code relying on shallow merging.
 *
 * @implements Rule<MethodCall>
 */
final class MergeIfMissingDotKeysRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'mergeifmissing') {
            return [];
        }

        // Check whether any argument string key contains a dot.
        foreach ($node->getArgs() as $arg) {
            if (! $arg->value instanceof Array_) {
                continue;
            }

            foreach ($arg->value->items as $item) {
                if ($item === null || ! $item->key instanceof String_) {
                    continue;
                }

                if (str_contains($item->key->value, '.')) {
                    return [
                        RuleErrorBuilder::message(
                            'mergeIfMissing() now supports dot notation for nested array merging.'
                        )->identifier('laravelUpgrade.mergeIfMissingDotKeys')
                            ->tip('Verify your code handles the new nested merging behaviour.')
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }
}
