<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12 keeps a null default for nullable class-typed constructor
 * parameters during container resolution. Restrict this advisory to the
 * framework classes and handler shapes Laravel resolves through its container.
 *
 * @implements Rule<Class_>
 */
final class ContainerDefaultParameterRule implements Rule
{
    /** @var list<string> */
    private const RESOLVED_BASE_CLASSES = [
        'Illuminate\\Routing\\Controller',
        'Illuminate\\Console\\Command',
        'Illuminate\\Notifications\\Notification',
        'Illuminate\\Mail\\Mailable',
        'Illuminate\\Support\\ServiceProvider',
    ];

    /** @var list<string> */
    private const RESOLVED_INTERFACES = [
        'Illuminate\\Contracts\\Queue\\ShouldQueue',
    ];

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Class_ || $node->isAnonymous() || ! $this->isContainerResolved($node, $scope)) {
            return [];
        }

        foreach ($node->stmts as $statement) {
            if (! $statement instanceof ClassMethod
                || $statement->name->toLowerString() !== '__construct') {
                continue;
            }

            foreach ($statement->params as $parameter) {
                if ($this->hasNullableClassDefault($parameter)) {
                    return [
                        RuleErrorBuilder::message(
                            'Laravel 12 container resolution now honours null defaults on class-typed constructor parameters.'
                        )->identifier('laravelUpgrade.containerDefaultParameter')
                            ->tip('Review this nullable dependency: the container now keeps the null default instead of resolving the class.')
                            ->line($statement->getStartLine())
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }

    private function isContainerResolved(Class_ $node, Scope $scope): bool
    {
        $reflection = $scope->getClassReflection();

        if ($reflection instanceof ClassReflection) {
            foreach (self::RESOLVED_BASE_CLASSES as $baseClass) {
                if ($reflection->is($baseClass)) {
                    return true;
                }
            }

            foreach (self::RESOLVED_INTERFACES as $interface) {
                if ($reflection->implementsInterface($interface)) {
                    return true;
                }
            }
        }

        if ($node->extends instanceof Name && $this->isKnownType($node->extends, $scope, self::RESOLVED_BASE_CLASSES)) {
            return true;
        }

        foreach ($node->implements as $interface) {
            if ($interface instanceof Name && $this->isKnownType($interface, $scope, self::RESOLVED_INTERFACES)) {
                return true;
            }
        }

        return $this->isApplicationContainerCategory($node, $scope, $reflection);
    }

    /** @param list<string> $knownTypes */
    private function isKnownType(Name $name, Scope $scope, array $knownTypes): bool
    {
        return in_array(ltrim($scope->resolveName($name), '\\'), $knownTypes, true);
    }

    private function isApplicationContainerCategory(Class_ $node, Scope $scope, ?ClassReflection $reflection): bool
    {
        $className = $reflection?->getName();

        if ($className === null && $node->name instanceof Identifier) {
            $namespace = trim($scope->getNamespace() ?? '', '\\');
            $className = ($namespace !== '' ? $namespace.'\\' : '').$node->name->toString();
        }

        if ($className === null) {
            return false;
        }

        $parts = explode('\\', $className);

        foreach (['Controllers', 'Jobs', 'Listeners', 'Commands', 'Notifications', 'Mail', 'Providers'] as $category) {
            if (in_array($category, $parts, true)) {
                return true;
            }
        }

        foreach ($parts as $index => $part) {
            if ($part === 'Http' && ($parts[$index + 1] ?? null) === 'Middleware') {
                return true;
            }
        }

        return false;
    }

    private function hasNullableClassDefault(Node\Param $parameter): bool
    {
        if (! $parameter->default instanceof ConstFetch
            || $parameter->default->name->toLowerString() !== 'null') {
            return false;
        }

        $type = $parameter->type;

        if ($type instanceof NullableType) {
            return $type->type instanceof Name;
        }

        if (! $type instanceof UnionType) {
            return false;
        }

        $hasClassType = false;
        $hasNullType = false;

        foreach ($type->types as $unionType) {
            if ($unionType instanceof Name) {
                $hasClassType = true;
            }

            if ($unionType instanceof Identifier && $unionType->toLowerString() === 'null') {
                $hasNullType = true;
            }
        }

        return $hasClassType && $hasNullType;
    }
}
