<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 12: Blueprint now receives its schema Connection as argument zero.
 *
 * @implements Rule<New_>
 */
final class BlueprintConstructorConnectionRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof New_
            || ! (new ObjectType('Illuminate\\Database\\Schema\\Blueprint'))
                ->isSuperTypeOf($scope->getType($node))->yes()) {
            return [];
        }

        if (isset($node->args[0]) && $node->args[0] instanceof Arg && $this->isConnection($node->args[0], $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Laravel 12 Blueprint constructors require a Connection as the first argument.'
            )->identifier('laravelUpgrade.blueprintConstructorConnection')
                ->tip('Pass the schema Connection before the table name and callback.')
                ->build(),
        ];
    }

    private function isConnection(Arg $argument, Scope $scope): bool
    {
        return (new ObjectType('Illuminate\\Database\\Connection'))
            ->isSuperTypeOf($scope->getType($argument->value))->yes();
    }
}
