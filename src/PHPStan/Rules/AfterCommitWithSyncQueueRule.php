<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: the queue 'after_commit' connection option is ignored for the
 * 'sync' driver. Flags config/queue.php files that set after_commit on a sync
 * connection.
 */
/**
 * @implements Rule<PropertyFetch>
 */
final class AfterCommitWithSyncQueueRule implements Rule
{
    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $filePath = str_replace('\\', '/', $scope->getFile());

        if (! str_ends_with($filePath, 'queue.php')) {
            return [];
        }

        if ($node->name->toLowerString() !== 'after_commit') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'The after_commit option is ignored by the sync queue driver.'
            )->identifier('laravelUpgrade.afterCommitWithSyncQueue')
                ->tip('Remove after_commit from sync connections or switch to a real driver.')
                ->build(),
        ];
    }
}
