<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Param;
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
        if (!$node instanceof Class_) {
            return null;
        }

        // Check if this class implements Enumerable contract
        $implementsEnumerable = false;
        if ($node->implements) {
            foreach ($node->implements as $implement) {
                if ($this->isName($implement, 'Illuminate\\Support\\Enumerable')) {
                    $implementsEnumerable = true;
                    break;
                }
            }
        }

        if (!$implementsEnumerable) {
            return null;
        }

        // Find dump method in this class
        $dumpMethod = null;
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $this->isName($stmt->name, 'dump')) {
                $dumpMethod = $stmt;
                break;
            }
        }

        if (!$dumpMethod instanceof ClassMethod) {
            return null;
        }

        // Check current parameter signature of dump method
        $hasVariadicArgs = false;
        foreach ($dumpMethod->params as $param) {
            if ($param instanceof Param && $param->variadic) {
                $hasVariadicArgs = true;
                break;
            }
        }

        // If it doesn't have variadic args, update the signature
        if (!$hasVariadicArgs) {
            // Add variadic parameter
            $variadicParam = new Param(
                var: new \PhpParser\Node\Expr\Variable('args'),
                type: null,
                byRef: false,
                variadic: true
            );

            $dumpMethod->params = [$variadicParam];

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update Enumerable contract dump method to accept variadic arguments for Laravel 11',
            [
                new CodeSample(
                    'public function dump()',
                    'public function dump(...$args)'
                ),
            ]
        );
    }
}