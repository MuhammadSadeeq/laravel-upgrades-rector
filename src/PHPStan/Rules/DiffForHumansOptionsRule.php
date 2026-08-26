<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Carbon 3 changed diffForHumans() option handling. Flags diffForHumans()
 * calls so developers verify the output matches expectations.
 *
 * @implements Rule<MethodCall>
 */
final class DiffForHumansOptionsRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'diffforhumans') {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType('Carbon\CarbonInterface'))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'diffForHumans() option handling changed in Carbon 3; verify the output format.'
            )->identifier('laravelUpgrade.diffForHumansOptions')
                ->tip('Verify parts, short syntax, and locale handling produce the expected result.')
                ->build(),
        ];
    }
}
