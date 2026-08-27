<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 11: Eloquent's Model now defines casts(). A relationship using that
 * name must be renamed, otherwise it collides with the model API.
 *
 * @implements Rule<Class_>
 */
final class EloquentCastsMethodConflictRule implements Rule
{
    /** @var list<string> */
    private const RELATIONSHIP_METHODS = [
        'hasmany', 'hasone', 'belongsto', 'belongstomany', 'morphmany', 'morphone',
        'morphto', 'morphtomany', 'morphedbymany', 'hasmanythrough', 'hasonethrough',
    ];

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

        if (! $this->isEloquentModel($node, $class)) {
            return [];
        }

        foreach ($node->stmts as $statement) {
            if (! $statement instanceof ClassMethod
                || $statement->name->toLowerString() !== 'casts'
                || ! $this->containsRelationshipCall($statement)) {
                continue;
            }

            return [
                RuleErrorBuilder::message(
                    'This Eloquent model defines a relationship named casts(), which conflicts with the Laravel 11 Model API.'
                )->identifier('laravelUpgrade.eloquentCastsMethodConflict')
                    ->tip('Rename the relationship method and update its call sites.')
                    ->build(),
            ];
        }

        return [];
    }

    private function isEloquentModel(Class_ $node, ?ClassReflection $class): bool
    {
        if ($class instanceof ClassReflection
            && ($class->is('Illuminate\\Database\\Eloquent\\Model')
                || $class->isSubclassOf('Illuminate\\Database\\Eloquent\\Model'))) {
            return true;
        }

        // PHPStan may not have an application class loaded when this rule is
        // exercised in isolation. A direct Model/FQCN parent is still an
        // unambiguous match and keeps the advisory useful in that context.
        return $node->extends instanceof Name
            && in_array(ltrim($node->extends->toString(), '\\'), [
                'Model',
                'Illuminate\\Database\\Eloquent\\Model',
            ], true);
    }

    private function containsRelationshipCall(ClassMethod $method): bool
    {
        foreach ($method->getSubNodeNames() as $property) {
            $value = $method->{$property};

            if ($this->containsRelationshipCallInValue($value)) {
                return true;
            }
        }

        return false;
    }

    private function containsRelationshipCallInValue(mixed $value): bool
    {
        if ($value instanceof MethodCall && $value->name instanceof Identifier
            && in_array($value->name->toLowerString(), self::RELATIONSHIP_METHODS, true)) {
            return true;
        }

        if ($value instanceof Node) {
            foreach ($value->getSubNodeNames() as $property) {
                if ($this->containsRelationshipCallInValue($value->{$property})) {
                    return true;
                }
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsRelationshipCallInValue($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
