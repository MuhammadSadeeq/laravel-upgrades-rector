<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: synchronous queue jobs now respect the after-commit setting on
 * the connection or job. The project queue default is passed in the generated
 * PHPStan configuration so this rule only reports relevant applications.
 */
/**
 * @implements Rule<Node>
 */
final class AfterCommitWithSyncQueueRule implements Rule
{
    public function __construct(private readonly ?string $queueDefault = null) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (strtolower((string) $this->queueDefault) !== 'sync') {
            return [];
        }

        $isAfterCommit = match (true) {
            $node instanceof MethodCall,
            $node instanceof PropertyFetch => $node->name instanceof Identifier
                && $node->name->toLowerString() === 'aftercommit',
            $node instanceof Property => $this->hasAfterCommitProperty($node),
            default => false,
        };

        if (! $isAfterCommit) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Laravel 11 synchronous queue jobs now respect after-commit settings.'
            )->identifier('laravelUpgrade.afterCommitWithSyncQueue')
                ->tip('Review transaction timing; use beforeCommit() or remove afterCommit when immediate execution is required.')
                ->build(),
        ];
    }

    private function hasAfterCommitProperty(Property $property): bool
    {
        foreach ($property->props as $propertyItem) {
            if ($propertyItem->name->toString() === 'afterCommit') {
                return true;
            }
        }

        return false;
    }
}
