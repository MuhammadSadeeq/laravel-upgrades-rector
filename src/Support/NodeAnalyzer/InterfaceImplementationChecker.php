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
    public function implementsInterface(Class_ $node, string $interfaceFqcn): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if ($scope instanceof Scope) {
            $classReflection = $scope->getClassReflection();

            if ($classReflection instanceof ClassReflection) {
                return $classReflection->is($interfaceFqcn);
            }
        }

        $shortName = substr($interfaceFqcn, (int) strrpos($interfaceFqcn, '\\') + 1);

        foreach ($node->implements as $implement) {
            $implementName = $implement->toString();

            if ($implementName === $interfaceFqcn || $implementName === $shortName) {
                return true;
            }
        }

        return false;
    }

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
