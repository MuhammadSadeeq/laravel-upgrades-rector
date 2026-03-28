<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateEnumerableContractRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $classReflection->is('Illuminate\\Support\\Enumerable')) {
            return null;
        }

        $dumpMethod = $this->findDumpMethod($node);

        if (! $dumpMethod instanceof ClassMethod) {
            return null;
        }

        if ($this->hasVariadicParam($dumpMethod)) {
            return null;
        }

        if ($dumpMethod->params !== []) {
            // Add warning comment instead of silently skipping
            $existingComments = $node->getComments();

            foreach ($existingComments as $comment) {
                if (str_contains($comment->getText(), 'Laravel 11: dump()')) {
                    return null;
                }
            }

            $newComment = new \PhpParser\Comment(
                '// Laravel 11: Enumerable::dump() signature changed to dump(...$args). Update this method signature manually.'
            );
            $node->setAttribute('comments', array_merge([$newComment], $existingComments));
            $node->setAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::ORIGINAL_NODE, null);

            return $node;
        }

        $variadicParam = new Param(
            var: new Variable('args'),
            variadic: true
        );

        $dumpMethod->params = [$variadicParam];

        return $node;
    }

    private function findDumpMethod(Class_ $class): ?ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $this->isName($stmt->name, 'dump')) {
                return $stmt;
            }
        }

        return null;
    }

    private function hasVariadicParam(ClassMethod $method): bool
    {
        foreach ($method->params as $param) {
            if ($param instanceof Param && $param->variadic) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update Enumerable contract dump() method to accept variadic arguments for Laravel 11',
            [
                new CodeSample(
                    'public function dump()',
                    'public function dump(...$args)'
                ),
            ]
        );
    }
}
