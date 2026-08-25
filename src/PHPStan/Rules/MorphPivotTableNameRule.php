<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13: polymorphic relations using ->morphToMany() or ->morphedByMany()
 * now require an explicit pivot table name when the default convention changes.
 *
 * @implements Rule<MethodCall>
 */
final class MorphPivotTableNameRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toLowerString();

        if ($methodName !== 'morphtomany' && $methodName !== 'morphedbymany') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Polymorphic pivot table naming changed in Laravel 13; verify the generated table name matches your schema.'
            )->identifier('laravelUpgrade.morphPivotTableName')
                ->tip('Pass an explicit table name to morphToMany()/morphedByMany() to pin the behaviour.')
                ->build(),
        ];
    }
}
