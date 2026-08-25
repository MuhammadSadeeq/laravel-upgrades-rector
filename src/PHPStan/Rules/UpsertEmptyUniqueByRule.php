<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * MySQL/MariaDB: upsert() with an empty uniqueBy array no longer works —
 * the database requires an explicit list of unique columns.
 *
 * @implements Rule<MethodCall>
 */
final class UpsertEmptyUniqueByRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'upsert') {
            return [];
        }

        $args = $node->getArgs();

        if (count($args) < 2) {
            return [];
        }

        $uniqueBy = $args[1]->value;

        if ($uniqueBy instanceof Array_ && count($uniqueBy->items) === 0) {
            return [
                RuleErrorBuilder::message(
                    'upsert() with an empty uniqueBy array is not supported by MySQL/MariaDB.'
                )->identifier('laravelUpgrade.upsertEmptyUniqueBy')
                    ->tip('Provide the actual unique column names as the second argument.')
                    ->build(),
            ];
        }

        return [];
    }
}
