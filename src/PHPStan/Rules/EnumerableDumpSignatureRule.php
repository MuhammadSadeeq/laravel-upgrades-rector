<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: Enumerable::dump() is variadic. Parameterized overrides that
 * are not variadic can no longer satisfy the contract and need a manual
 * signature/body review; the Rector only forwards no-argument overrides.
 *
 * @implements Rule<Class_>
 */
final class EnumerableDumpSignatureRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Class_ || $node->isAnonymous()) {
            return [];
        }

        $reflection = $scope->getClassReflection();
        if (! $this->isEnumerable($node, $scope, $reflection)) {
            return [];
        }

        foreach ($node->stmts as $statement) {
            if (! $statement instanceof ClassMethod
                || $statement->name->toLowerString() !== 'dump') {
                continue;
            }

            if (count($statement->params) === 0
                || ! $this->hasNonVariadicParameter($statement)) {
                return [];
            }

            return [
                RuleErrorBuilder::message(
                    'Enumerable::dump() overrides must accept variadic arguments in Laravel 11.'
                )->identifier('laravelUpgrade.enumerableDumpSignature')
                    ->tip('Review this parameterized dump() override and forward ...$args; the Rector leaves it unchanged.')
                    ->build(),
            ];
        }

        return [];
    }

    private function isEnumerable(Class_ $node, Scope $scope, ?ClassReflection $reflection): bool
    {
        if ($reflection instanceof ClassReflection
            && $reflection->implementsInterface('Illuminate\\Support\\Enumerable')) {
            return true;
        }

        foreach ($node->implements as $interface) {
            if ($interface instanceof Name
                && strcasecmp(
                    ltrim($scope->resolveName($interface), '\\'),
                    'Illuminate\\Support\\Enumerable',
                ) === 0) {
                return true;
            }
        }

        return false;
    }

    private function hasNonVariadicParameter(ClassMethod $method): bool
    {
        foreach ($method->params as $parameter) {
            if (! $parameter->variadic) {
                return true;
            }
        }

        return false;
    }
}
