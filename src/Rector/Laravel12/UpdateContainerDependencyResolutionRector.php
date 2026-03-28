<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Param;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateContainerDependencyResolutionRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ClassMethod) {
            return null;
        }

        // Only check constructors
        if (!$this->isName($node->name, '__construct')) {
            return null;
        }

        $hasAffectedParams = false;

        foreach ($node->params as $param) {
            if ($this->isAffectedParameter($param)) {
                $hasAffectedParams = true;
                break;
            }
        }

        if (!$hasAffectedParams) {
            return null;
        }

        // Add a comment to the constructor
        $node->setAttribute('comments', [
            new \PhpParser\Comment\Doc(
                "/** Laravel 12: Container now respects default values for constructor parameters. " .
                "Class-typed parameters with default values will use the default instead of being resolved from the container. */"
            )
        ]);

        return $node;
    }

    private function isAffectedParameter(Param $param): bool
    {
        // Must have a default value
        if ($param->default === null) {
            return false;
        }

        // Must have a type hint
        if ($param->type === null) {
            return false;
        }

        // Check if the type is a class (not a built-in type like string, int, etc.)
        if ($param->type instanceof Name) {
            // It's a class type
            return true;
        }

        // Check for nullable types (e.g., ?Carbon)
        if ($param->type instanceof Node\NullableType) {
            return $param->type->type instanceof Name;
        }

        // Check for union types that include class types
        if ($param->type instanceof Node\UnionType) {
            foreach ($param->type->types as $type) {
                if ($type instanceof Name) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add documentation for container dependency resolution behavior change in Laravel 12',
            [
                new CodeSample(
                    'class Example {
    public function __construct(public ?Carbon $date = null) {}
}',
                    '/** Laravel 12: Container now respects default values for constructor parameters. Class-typed parameters with default values will use the default instead of being resolved from the container. */
class Example {
    public function __construct(public ?Carbon $date = null) {}
}'
                ),
            ]
        );
    }
}
