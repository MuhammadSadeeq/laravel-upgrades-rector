<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 13: Container::call() now respects nullable class-typed default
 * parameters when resolving dependencies. Flags Container::call() invocations
 * so developers verify their constructors behave as expected.
 */
/**
 * @implements Rule<MethodCall>
 */
final class ContainerCallNullableDefaultRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'call') {
            return [];
        }

        if (! $node->var instanceof Variable) {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType('Illuminate\Contracts\Container\Container'))->isSuperTypeOf($type)->yes()
            && ! (new ObjectType('Illuminate\Container\Container'))->isSuperTypeOf($type)->yes()
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Container::call() now respects nullable class-typed default parameters when resolving.'
            )->identifier('laravelUpgrade.containerCallNullableDefault')
                ->tip('Verify that nullable constructor parameters resolve to the expected value.')
                ->build(),
        ];
    }
}
