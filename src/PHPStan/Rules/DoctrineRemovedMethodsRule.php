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
 * Laravel 11 removed the schema-layer Doctrine DBAL bridge from typed
 * Connection receivers.
 *
 * @implements Rule<MethodCall>
 */
final class DoctrineRemovedMethodsRule implements Rule
{
    /** @var array<string, string> */
    private const REPLACEMENTS = [
        'getdoctrineconnection' => 'use the native connection API',
        'getdoctrineschemamanager' => 'use Schema inspection methods',
        'getdoctrinecolumn' => 'use Schema::getColumnType() or native schema inspection',
        'registerdoctrinetype' => 'use a native database type or migration',
        'isdoctrineavailable' => 'remove the Doctrine availability check',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall || ! $node->name instanceof Identifier) {
            return [];
        }

        $method = $node->name->toLowerString();
        $replacement = self::REPLACEMENTS[$method] ?? null;

        if ($replacement === null) {
            return [];
        }

        $type = $scope->getType($node->var);
        if (! (new ObjectType('Illuminate\\Database\\Connection'))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf('%s() was removed from Laravel 11 (high-confidence).', $node->name->toString())
            )->identifier('laravelUpgrade.doctrineRemovedMethods')
                ->tip('Doctrine DBAL schema methods are gone; '.$replacement.'.')
                ->build(),
        ];
    }
}
