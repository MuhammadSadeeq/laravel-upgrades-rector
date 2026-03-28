<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class InterfaceImplementationChecker
{
    /**
     * Check if a class implements the given interface, including through parent classes.
     */
    public function implementsInterface(Class_ $node, string $interfaceFqcn): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return false;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return false;
        }

        return $classReflection->is($interfaceFqcn);
    }

    /**
     * Check if a class already has a specific method.
     */
    public function hasMethod(Class_ $node, string $methodName): bool
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->name === $methodName) {
                return true;
            }
        }

        return false;
    }
}
