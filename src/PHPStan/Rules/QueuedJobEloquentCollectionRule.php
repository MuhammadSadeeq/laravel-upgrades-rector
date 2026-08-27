<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 13: queued jobs carrying an Eloquent collection need review because
 * collection relation serialization differs from ordinary collections.
 *
 * @implements Rule<Class_>
 */
final class QueuedJobEloquentCollectionRule implements Rule
{
    private const SHOULD_QUEUE = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    private const ELOQUENT_COLLECTION = 'Illuminate\\Database\\Eloquent\\Collection';

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Class_ || $node->isAnonymous() || ! $this->isQueuedJob($node, $scope)) {
            return [];
        }

        $errors = [];

        foreach ($node->stmts as $statement) {
            if ($statement instanceof Property && $this->isEloquentCollection($statement->type, $scope)) {
                $errors[] = $this->error($statement->getStartLine());
            }

            if (! $statement instanceof ClassMethod
                || $statement->name->toLowerString() !== '__construct') {
                continue;
            }

            foreach ($statement->params as $parameter) {
                if ($parameter->isPromoted() && $this->isEloquentCollection($parameter->type, $scope)) {
                    $errors[] = $this->error($statement->getStartLine());
                }
            }
        }

        return $errors;
    }

    private function isQueuedJob(Class_ $node, Scope $scope): bool
    {
        $reflection = $scope->getClassReflection();

        if ($reflection instanceof ClassReflection && $reflection->implementsInterface(self::SHOULD_QUEUE)) {
            return true;
        }

        foreach ($node->implements as $interface) {
            if ($interface instanceof Name
                && ltrim($scope->resolveName($interface), '\\') === self::SHOULD_QUEUE) {
                return true;
            }
        }

        return false;
    }

    private function isEloquentCollection(?Node $type, Scope $scope): bool
    {
        if ($type instanceof Name) {
            return ltrim($scope->resolveName($type), '\\') === self::ELOQUENT_COLLECTION;
        }

        if ($type instanceof NullableType) {
            return $this->isEloquentCollection($type->type, $scope);
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $unionType) {
                if ($this->isEloquentCollection($unionType, $scope)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function error(int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'Queued jobs carrying an Eloquent Collection require review in Laravel 13.'
        )->identifier('laravelUpgrade.queuedJobEloquentCollection')
            ->tip('Prefer a model identifier or a plain collection payload when serializing queued jobs.')
            ->line($line)
            ->build();
    }
}
