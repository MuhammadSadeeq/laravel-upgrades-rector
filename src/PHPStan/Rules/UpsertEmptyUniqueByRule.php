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
use PHPStan\Type\ObjectType;

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

        if (! $this->isQueryBuilder($node, $scope)) {
            return [];
        }

        $uniqueBy = $this->uniqueByArgument($node);

        if ($uniqueBy instanceof Array_ && $uniqueBy->items === []
            || $uniqueBy instanceof String_ && $uniqueBy->value === '') {
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

    private function isQueryBuilder(MethodCall $call, Scope $scope): bool
    {
        $type = $scope->getType($call->var);

        return (new ObjectType('Illuminate\\Database\\Query\\Builder'))->isSuperTypeOf($type)->yes()
            || (new ObjectType('Illuminate\\Database\\Eloquent\\Builder'))->isSuperTypeOf($type)->yes();
    }

    private function uniqueByArgument(MethodCall $call): ?Node
    {
        foreach ($call->getArgs() as $index => $argument) {
            if ($argument->name instanceof Identifier
                && $argument->name->toLowerString() === 'uniqueby') {
                return $argument->value;
            }

            if ($index === 1) {
                return $argument->value;
            }
        }

        return null;
    }
}
