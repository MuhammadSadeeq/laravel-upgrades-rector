<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13: queued notifications without DeleteWhenMissingModels will
 * silently drop when the subject model is deleted before the queue processes
 * them. Flags queued notification classes that do not opt into deletion.
 */
/**
 * @implements Rule<Class_>
 */
final class QueuedNotificationMissingModelsRule implements Rule
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

        if (! $this->isNotification($node, $scope)
            || ! $this->isQueued($node, $scope)
            || $this->hasDeleteWhenMissingModelsPolicy($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Queued notifications may fail when their subject model is missing in Laravel 13.'
            )->identifier('laravelUpgrade.queuedNotificationMissingModels')
                ->tip('Add #[DeleteWhenMissingModels] or set $deleteWhenMissingModels = true when missing models should delete the job.')
                ->build(),
        ];
    }

    private function isNotification(Class_ $node, Scope $scope): bool
    {
        $reflection = $scope->getClassReflection();

        if ($reflection instanceof ClassReflection
            && ($reflection->is('Illuminate\\Notifications\\Notification')
                || $reflection->isSubclassOf('Illuminate\\Notifications\\Notification'))) {
            return true;
        }

        return $node->extends instanceof Name
            && strcasecmp(
                ltrim($scope->resolveName($node->extends), '\\'),
                'Illuminate\\Notifications\\Notification',
            ) === 0;
    }

    private function isQueued(Class_ $node, Scope $scope): bool
    {
        $reflection = $scope->getClassReflection();

        if ($reflection instanceof ClassReflection
            && $reflection->implementsInterface('Illuminate\\Contracts\\Queue\\ShouldQueue')) {
            return true;
        }

        foreach ($node->implements as $interface) {
            if ($interface instanceof Name
                && strcasecmp(
                    ltrim($scope->resolveName($interface), '\\'),
                    'Illuminate\\Contracts\\Queue\\ShouldQueue',
                ) === 0) {
                return true;
            }
        }

        return false;
    }

    private function hasDeleteWhenMissingModelsPolicy(Class_ $node, Scope $scope): bool
    {
        foreach ($node->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                if (strcasecmp(
                    ltrim($scope->resolveName($attribute->name), '\\'),
                    'Illuminate\\Queue\\Attributes\\DeleteWhenMissingModels',
                ) === 0) {
                    return true;
                }
            }
        }

        foreach ($node->stmts as $statement) {
            if (! $statement instanceof Property) {
                continue;
            }

            foreach ($statement->props as $property) {
                if ($property->name->toLowerString() !== 'deletewhenmissingmodels') {
                    continue;
                }

                return $property->default instanceof Node\Expr\ConstFetch
                    && $property->default->name->toLowerString() === 'true';
            }
        }

        return false;
    }
}
