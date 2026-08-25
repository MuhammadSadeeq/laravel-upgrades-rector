<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12: Schema inspection methods (getTables, getViews, getTypes)
 * now return results from ALL schemas by default instead of the current one.
 *
 * @implements Rule<StaticCall>
 */
final class SchemaInspectionAllSchemasRule implements Rule
{
    private const INSPECTION_METHODS = ['getTables', 'getViews', 'getTypes'];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toLowerString();

        if (! in_array($methodName, array_map('strtolower', self::INSPECTION_METHODS), true)) {
            return [];
        }

        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($node->class->toString(), '\\');

        if ($raw !== 'Illuminate\Support\Facades\Schema' && $raw !== 'Schema') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Schema inspection methods now return results from all schemas by default.'
            )->identifier('laravelUpgrade.schemaInspectionAllSchemas')
                ->tip('Pass a schema name to limit results to a specific schema.')
                ->build(),
        ];
    }
}
