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

            if (getenv('II_DEBUG') === '1') {
                $native = $classReflection->getNativeReflection();
                $implList = array_map(static fn ($i): string => $i->getName(), $native->getInterfaces());
                @file_put_contents('/tmp/ii-debug.log', sprintf(
                    "[implements] fqcn=%s native=%s implList=%s is()=%s\n",
                    $classReflection->getName(),
                    var_export($native->implementsInterface($interfaceFqcn), true),
                    implode(',', $implList),
                    var_export($classReflection->is($interfaceFqcn), true)
                ), FILE_APPEND);
            }

            if ($classReflection instanceof ClassReflection && $classReflection->is($interfaceFqcn)) {
                return true;
            }
        }

        // Fallback for scopes without reflection: resolve through the scope
        // (which honours use-statements). A short-name guess would match
        // unrelated classes.
        foreach ($node->implements as $implement) {
            $resolved = $this->resolveName($scope, $implement);

            if (getenv('II_DEBUG') === '1') {
                @file_put_contents('/tmp/ii-debug.log', sprintf(
                    "[fallback] impl=%s resolved=%s fqcn=%s eq=%d\n",
                    $implement->toString(),
                    var_export($resolved, true),
                    var_export($interfaceFqcn, true),
                    var_export($resolved === $interfaceFqcn, true)
                ), FILE_APPEND);
            }

            if ($resolved === $interfaceFqcn) {
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

        if (! $classReflection instanceof ClassReflection || ! $classReflection->hasMethod($methodName)) {
            return false;
        }

        // Concrete providers win over interface declarations: both PHP and
        // PHPStan resolve ::getMethod() to the interface declaration even when
        // a parent class or trait provides a body, so the hierarchy must be
        // walked explicitly.
        //
        // The walk starts at PARENTS on purpose: Rector's kernel caches
        // PHPStan reflections by class name per process, and multiple fixtures
        // reuse one class name — the analyzed class's own truth is the local
        // AST scan above.
        // Traits used by the analysed class itself count as providers (the
        // stock User model relies on Illuminate\Auth\Authenticatable); they
        // are safe to consult here because trait names differ per fixture.
        foreach ($classReflection->getTraits() as $selfTrait) {
            if ($selfTrait->hasNativeMethod($methodName)) {
                return true;
            }
        }

        $parent = $classReflection->getParentClass();

        while ($parent instanceof ClassReflection) {
            if (! $parent->isInterface()
                && $parent->hasNativeMethod($methodName)
                && ! $parent->getNativeMethod($methodName)->isAbstract()
            ) {
                return true;
            }

            foreach ($parent->getTraits() as $traitReflection) {
                if ($traitReflection->hasNativeMethod($methodName)) {
                    return true;
                }
            }

            $parent = $parent->getParentClass();
        }

        // Last resort for parents PHPStan could not reflect (e.g. classes
        // known only through the project autoloader): resolve the explicitly
        // extended class and inspect it. Only the parent FQCN is touched —
        // never the analysed user class itself.
        return $this->hasMethodOnAutoloadableParent($node, $methodName, $scope);
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
