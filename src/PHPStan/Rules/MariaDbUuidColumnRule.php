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
 * Laravel 11: the MariaDB driver creates native UUID columns for uuid().
 * Flags uuid() calls on Blueprint receivers when the project may use MariaDB,
 * since the generated column type differs from MySQL.
 */
/**
 * @implements Rule<MethodCall>
 */
final class MariaDbUuidColumnRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'uuid') {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType('Illuminate\Database\Schema\Blueprint'))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'The new MariaDB driver creates native UUID columns for uuid(); the column type differs from MySQL.'
            )->identifier('laravelUpgrade.mariaDbUuidColumn')
                ->tip('Use char(36) instead if you switch to the mariadb driver and need the previous behaviour.')
                ->build(),
        ];
    }
}
