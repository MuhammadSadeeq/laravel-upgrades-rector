<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 12: Schema inspection methods (getTables, getViews, getTypes)
 * now return results from ALL schemas by default instead of the current one.
 *
 * @implements Rule<Node>
 */
final class SchemaInspectionAllSchemasRule implements Rule
{
    private const INSPECTION_METHODS = ['getTables', 'getTableListing', 'getViews', 'getTypes'];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof StaticCall && $this->isSchemaStaticInspection($node)) {
            return $node->args === [] ? [$this->error()] : [];
        }

        if ($node instanceof MethodCall && $this->isSchemaBuilderInspection($node, $scope)) {
            return $node->args === [] ? [$this->error()] : [];
        }

        return [];
    }

    private function isSchemaStaticInspection(StaticCall $call): bool
    {
        return $this->isInspectionMethod($call->name)
            && $call->class instanceof Name
            && $this->isSchemaName($call->class);
    }

    private function isSchemaBuilderInspection(MethodCall $call, Scope $scope): bool
    {
        if (! $this->isInspectionMethod($call->name)) {
            return false;
        }

        if ($call->var instanceof StaticCall
            && $call->var->name instanceof Identifier
            && $call->var->name->toLowerString() === 'connection'
            && $call->var->class instanceof Name
            && $this->isSchemaName($call->var->class)) {
            return true;
        }

        return (new ObjectType('Illuminate\Database\Schema\Builder'))
            ->isSuperTypeOf($scope->getType($call->var))->yes();
    }

    private function isInspectionMethod(Node $name): bool
    {
        return $name instanceof Identifier
            && in_array($name->toLowerString(), array_map('strtolower', self::INSPECTION_METHODS), true);
    }

    private function isSchemaName(Name $name): bool
    {
        return in_array(ltrim($name->toString(), '\\'), [
            'Illuminate\Support\Facades\Schema',
            'Schema',
        ], true);
    }

    private function error(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'Schema inspection methods now return results from all schemas by default.'
        )->identifier('laravelUpgrade.schemaInspectionAllSchemas')
            ->tip('Pass a schema name to limit results to a specific schema.')
            ->build();
    }
}
