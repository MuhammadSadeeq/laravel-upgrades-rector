<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateEnumerableDumpSignatureRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Support\Enumerable';

    public function __construct(
        private readonly InterfaceImplementationChecker $checker,
    ) {}

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if (! $this->checker->implementsInterface($node, self::INTERFACE_NAME)) {
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
            // Existing parameters may carry application-specific semantics;
            // do not rewrite them or attach advisory comments.
            return null;
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
                    <<<'CODE_SAMPLE'
class CustomCollection implements Enumerable
{
    public function dump()
    {
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class CustomCollection implements Enumerable
{
    public function dump(...$args)
    {
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
