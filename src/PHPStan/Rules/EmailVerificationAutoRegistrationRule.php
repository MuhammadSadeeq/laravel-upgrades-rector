<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11 automatically registers SendEmailVerificationNotification from
 * EventServiceProvider. Existing custom listener registration is safe.
 *
 * @implements Rule<Class_>
 */
final class EmailVerificationAutoRegistrationRule implements Rule
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

        $class = $scope->getClassReflection();

        if (! $this->isEventServiceProvider($node, $class)) {
            return [];
        }

        foreach ($node->stmts as $statement) {
            if ($statement instanceof ClassMethod && $statement->name->toLowerString() === 'configureemailverification') {
                return [];
            }
        }

        foreach ($node->stmts as $statement) {
            if ($statement instanceof Property && $this->isListenProperty($statement)
                && $this->hasRegisteredListener($statement)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Laravel 11 auto-registers SendEmailVerificationNotification from EventServiceProvider.'
            )->identifier('laravelUpgrade.emailVerificationAutoRegistration')
                ->tip('Review custom Registered listeners; define configureEmailVerification() if you need to opt out.')
                ->build(),
        ];
    }

    private function isEventServiceProvider(Class_ $node, ?ClassReflection $class): bool
    {
        if ($class instanceof ClassReflection
            && ($class->is('Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider')
                || $class->isSubclassOf('Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider'))) {
            return true;
        }

        // Preserve a conservative direct-parent fallback for isolated
        // PHPStan rule tests where the application class is not reflected.
        return $node->extends instanceof Name
            && in_array(ltrim($node->extends->toString(), '\\'), [
                'EventServiceProvider',
                'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider',
            ], true);
    }

    private function isListenProperty(Property $property): bool
    {
        foreach ($property->props as $item) {
            if ($item->name->toLowerString() === 'listen') {
                return true;
            }
        }

        return false;
    }

    private function hasRegisteredListener(Property $property): bool
    {
        $default = $property->props[0]->default ?? null;

        if (! $default instanceof Array_) {
            return false;
        }

        foreach ($default->items as $item) {
            if (! $item instanceof ArrayItem || $item->key === null) {
                continue;
            }

            if ($item->key instanceof String_ && $item->key->value === 'Illuminate\\Auth\\Events\\Registered') {
                return true;
            }

            if ($item->key instanceof ClassConstFetch
                && $item->key->class instanceof Name
                && $item->key->class->toString() === 'Illuminate\\Auth\\Events\\Registered'
                && $item->key->name instanceof Identifier
                && $item->key->name->toString() === 'class') {
                return true;
            }
        }

        return false;
    }
}
