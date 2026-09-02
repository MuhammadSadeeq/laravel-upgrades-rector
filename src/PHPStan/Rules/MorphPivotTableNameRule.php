<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 13: custom MorphPivot/Pivot classes passed to polymorphic relations
 * now infer pluralized table names. Applications that relied on the old
 * singular name should define the table explicitly on the pivot model.
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

        if ($node->name->toLowerString() !== 'using') {
            return [];
        }

        $relation = $this->findMorphRelation($node);

        if ($relation === null
            || ! (new ObjectType('Illuminate\\Database\\Eloquent\\Model'))
                ->isSuperTypeOf($scope->getType($relation->var))->yes()
            || $this->hasExplicitTable($relation)
            || ! $this->hasCustomPivotClass($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Custom MorphPivot/Pivot table names are pluralized differently in Laravel 13.'
            )->identifier('laravelUpgrade.morphPivotTableName')
                ->tip('Define protected $table on the custom pivot model to preserve its previous table name.')
                ->build(),
        ];
    }

    private function findMorphRelation(MethodCall $using): ?MethodCall
    {
        $candidate = $using->var;

        while ($candidate instanceof MethodCall) {
            if (! $candidate->name instanceof Identifier) {
                return null;
            }

            $methodName = $candidate->name->toLowerString();

            if ($methodName === 'morphtomany' || $methodName === 'morphedbymany') {
                return $candidate;
            }

            $candidate = $candidate->var;
        }

        return null;
    }

    private function hasExplicitTable(MethodCall $relation): bool
    {
        foreach ($relation->getArgs() as $index => $argument) {
            if ($argument->name instanceof Identifier
                && $argument->name->toLowerString() === 'table') {
                return ! $this->isNull($argument->value);
            }

            if ($index === 2) {
                return ! $this->isNull($argument->value);
            }
        }

        return false;
    }

    private function isNull(Node $node): bool
    {
        return $node instanceof Expr\ConstFetch
            && $node->name->toLowerString() === 'null';
    }

    private function hasCustomPivotClass(MethodCall $using, Scope $scope): bool
    {
        $argument = $using->getArgs()[0] ?? null;

        return $argument !== null
            && $this->isPivotClass($argument->value, $scope)
            && ! $this->hasExplicitPivotTable($argument->value, $scope);
    }

    private function hasExplicitPivotTable(Expr $pivotExpression, Scope $scope): bool
    {
        $pivotType = $scope->getType($pivotExpression)->getClassStringObjectType();

        foreach ($pivotType->getObjectClassReflections() as $reflection) {
            $defaults = $reflection->getNativeReflection()->getDefaultProperties();

            if (isset($defaults['table']) && is_string($defaults['table']) && $defaults['table'] !== '') {
                return true;
            }
        }

        return false;
    }

    private function isPivotClass(Node $node, Scope $scope): bool
    {
        if (! $node instanceof ClassConstFetch
            || ! $node->class instanceof Name
            || ! $node->name instanceof Identifier
            || $node->name->toLowerString() !== 'class') {
            return false;
        }

        $resolved = ltrim($scope->resolveName($node->class), '\\');

        return (new ObjectType('Illuminate\\Database\\Eloquent\\Relations\\MorphPivot'))
            ->isSuperTypeOf(new ObjectType($resolved))->yes()
            || (new ObjectType('Illuminate\\Database\\Eloquent\\Relations\\Pivot'))
                ->isSuperTypeOf(new ObjectType($resolved))->yes();
    }
}
