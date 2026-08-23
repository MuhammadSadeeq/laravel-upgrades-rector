<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Answers "does this class already satisfy X?" using PHPStan's reflection
 * (which sees traits and parents) and, only for explicitly named parent
 * classes from vendor/framework namespaces, the Composer autoloader.
 *
 * Interface declarations never count as provided implementations.
 */
final class InterfaceImplementationChecker
{
    public function implementsInterface(Class_ $node, string $interfaceFqcn): bool
    {
        $scopeAttribute = $node->getAttribute(AttributeKey::SCOPE);
        $scope = $scopeAttribute instanceof Scope ? $scopeAttribute : null;

        if ($scope instanceof Scope) {
            $classReflection = $scope->getClassReflection();

            if ($classReflection instanceof ClassReflection && $classReflection->is($interfaceFqcn)) {
                return true;
            }
        }

        // Fallback for scopes without reflection: resolve through the scope
        // (which honours use-statements). A short-name guess would match
        // unrelated classes.
        foreach ($node->implements as $implement) {
            if ($this->resolveName($scope, $implement) === $interfaceFqcn) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the method is provided by the class itself, a parent or a used
     * trait. Methods that only exist on an interface (or are abstract) count
     * as missing — they still need an implementation.
     */
    public function hasMethod(Class_ $node, string $methodName): bool
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->name === $methodName) {
                return true;
            }
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return false;
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection instanceof ClassReflection && $this->hasMethodInReflection(
            $classReflection,
            $methodName
        )) {
            return true;
        }

        // Last resort for parents PHPStan could not reflect (e.g. classes
        // known only through the project autoloader): resolve the explicitly
        // extended class and inspect it. Only the parent FQCN is touched —
        // never the analysed user class itself.
        return $this->hasMethodOnAutoloadableParent($node, $methodName, $scope);
    }

    private function hasMethodInReflection(ClassReflection $classReflection, string $methodName): bool
    {
        // Concrete providers win over interface declarations: both PHP and
        // PHPStan resolve ::getMethod() to the interface declaration even when
        // a parent class or trait provides a body, so the hierarchy must be
        // checked explicitly.
        $current = $classReflection;

        while ($current instanceof ClassReflection) {
            if (! $current->isInterface()
                && $current->hasNativeMethod($methodName)
                && ! $current->getNativeMethod($methodName)->isAbstract()
            ) {
                return true;
            }

            $current = $current->getParentClass();
        }

        return false;
    }

    private function hasMethodOnAutoloadableParent(Class_ $node, string $methodName, ?Scope $scope): bool
    {
        if (! $node->extends instanceof Name) {
            return false;
        }

        $parentName = $this->resolveName($scope, $node->extends);

        if (! str_contains($parentName, '\\') || str_starts_with($parentName, 'App\\')) {
            return false;
        }

        if (! class_exists($parentName)) {
            return false;
        }

        $reflection = new \ReflectionClass($parentName);

        while ($reflection !== false) {
            if ($reflection->isInterface()) {
                return false;
            }

            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);

                if (! $method->isAbstract()) {
                    return true;
                }
            }

            $reflection = $reflection->getParentClass();
        }

        return false;
    }

    private function resolveName(?Scope $scope, Name $name): string
    {
        if ($scope instanceof Scope) {
            try {
                return $scope->resolveName($name);
            } catch (\Throwable) {
                // fall through to the raw name below
            }
        }

        return $name->toString();
    }
}
