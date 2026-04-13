<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use ReflectionClass;
use ReflectionMethod;

final class InterfaceImplementationChecker
{
    public function implementsInterface(Class_ $node, string $interfaceFqcn): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if ($scope instanceof Scope) {
            $classReflection = $scope->getClassReflection();

            if ($classReflection instanceof ClassReflection && $classReflection->is($interfaceFqcn)) {
                return true;
            }
        }

        $shortName = substr($interfaceFqcn, (int) strrpos($interfaceFqcn, '\\') + 1);

        foreach ($node->implements as $implement) {
            $implementName = $this->resolveName($implement);

            if ($implementName === $interfaceFqcn || $implementName === $shortName) {
                return true;
            }
        }

        return false;
    }

    private function resolveName(Name $name): string
    {
        $resolvedName = $name->getAttribute('resolvedName');

        if ($resolvedName instanceof Name) {
            return $resolvedName->toString();
        }

        return $name->toString();
    }

    public function hasMethod(Class_ $node, string $methodName): bool
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->name === $methodName) {
                return true;
            }
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if ($scope instanceof Scope) {
            $classReflection = $scope->getClassReflection();

            if ($classReflection instanceof ClassReflection) {
                $nativeReflection = $classReflection->getNativeReflection();

                if ($nativeReflection->hasMethod($methodName)) {
                    $reflectionMethod = $nativeReflection->getMethod($methodName);

                    if (! $reflectionMethod->getDeclaringClass()->isInterface()) {
                        return true;
                    }
                }

                $parentClassReflection = $classReflection->getParentClass();

                while ($parentClassReflection instanceof ClassReflection) {
                    if ($parentClassReflection->hasNativeMethod($methodName)
                        && ! $parentClassReflection->getNativeMethod($methodName)->isAbstract()) {
                        return true;
                    }

                    $parentClassReflection = $parentClassReflection->getParentClass();
                }
            }
        }

        if ($this->hasConcreteMethodOnResolvedParentChain($node, $methodName)) {
            return true;
        }

        return false;
    }

    private function hasConcreteMethodOnResolvedParentChain(Class_ $node, string $methodName): bool
    {
        if (! $node->extends instanceof \PhpParser\Node\Name) {
            return false;
        }

        $resolvedName = $node->extends->getAttribute('resolvedName');

        $parentClassName = $resolvedName instanceof \PhpParser\Node\Name
            ? $resolvedName->toString()
            : $node->extends->toString();

        if (! class_exists($parentClassName)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($parentClassName);

        while ($reflectionClass !== false) {
            if ($reflectionClass->hasMethod($methodName)) {
                $reflectionMethod = $reflectionClass->getMethod($methodName);

                if ($this->isConcreteClassMethod($reflectionMethod)) {
                    return true;
                }
            }

            $reflectionClass = $reflectionClass->getParentClass();
        }

        return false;
    }

    private function isConcreteClassMethod(ReflectionMethod $reflectionMethod): bool
    {
        return ! $reflectionMethod->isAbstract() && ! $reflectionMethod->getDeclaringClass()->isInterface();
    }
}
