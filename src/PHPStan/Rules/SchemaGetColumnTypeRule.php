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
 * Laravel 11: Schema::getColumnType() returns the native column type rather
 * than the Doctrine DBAL equivalent used by older Laravel versions.
 *
 * @implements Rule<Node>
 */
final class SchemaGetColumnTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof StaticCall && $this->isSchemaStaticCall($node)) {
            return [$this->error()];
        }

        if ($node instanceof MethodCall && $this->isSchemaBuilderCall($node, $scope)) {
            return [$this->error()];
        }

        return [];
    }

    private function isSchemaStaticCall(StaticCall $call): bool
    {
        return $call->name instanceof Identifier
            && $call->name->toLowerString() === 'getcolumntype'
            && $call->class instanceof Name
            && in_array(ltrim($call->class->toString(), '\\'), [
                'Schema',
                'Illuminate\\Support\\Facades\\Schema',
            ], true);
    }

    private function isSchemaBuilderCall(MethodCall $call, Scope $scope): bool
    {
        return $call->name instanceof Identifier
            && $call->name->toLowerString() === 'getcolumntype'
            && (new ObjectType('Illuminate\\Database\\Schema\\Builder'))
                ->isSuperTypeOf($scope->getType($call->var))->yes();
    }

    private function error(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'Schema::getColumnType() now returns the native column type instead of the Doctrine DBAL equivalent.'
        )->identifier('laravelUpgrade.schemaGetColumnType')
            ->tip('Review comparisons and mappings that expect Doctrine DBAL type names.')
            ->build();
    }
}
