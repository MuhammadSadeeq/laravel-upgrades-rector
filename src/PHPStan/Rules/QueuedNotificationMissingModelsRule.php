<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13: queued notifications without DeleteWhenMissingModels will
 * silently drop when the subject model is deleted before the queue processes
 * them. Flags queued notification classes that don't use the trait.
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

        // Must extend Illuminate\Notifications\Notifications.
        $extendsNotification = false;

        if ($node->extends instanceof Name) {
            $resolved = ltrim($node->extends->toString(), '\\');

            if (strcasecmp($resolved, 'Illuminate\Notifications\Notification') === 0) {
                $extendsNotification = true;
            }
        }

        if (! $extendsNotification) {
            return [];
        }

        $hasQueueable = false;
        $hasDeleteWhenMissingModels = false;

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    $traitName = ltrim($trait->toString(), '\\');

                    if (strcasecmp($traitName, 'Illuminate\Bus\Queueable') === 0
                        || strcasecmp($traitName, 'Queueable') === 0) {
                        $hasQueueable = true;
                    }

                    if (strcasecmp($traitName, 'Illuminate\Queue\InteractsWithQueue') === 0) {
                        $hasQueueable = true;
                    }

                    if (strcasecmp($traitName, 'Illuminate\Notifications\Notifiable') === 0) {
                        $hasQueueable = true;
                    }
                }
            }
        }

        if (! $hasQueueable) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Queued notifications without DeleteWhenMissingModels silently drop when the subject model is deleted.'
            )->identifier('laravelUpgrade.queuedNotificationMissingModels')
                ->tip('Add DeleteWhenMissingModels to prevent silent drops, or use ShouldBeUnique.')
                ->build(),
        ];
    }
}
