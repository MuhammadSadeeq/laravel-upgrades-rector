<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateDatabaseConnectionInterfaceRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Database\ConnectionInterface';

    private const METHOD_NAME = 'scalar';

    public function __construct(
        private readonly InterfaceImplementationChecker $checker,
    ) {
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_) {
            return null;
        }

        if (!$this->checker->implementsInterface($node, self::INTERFACE_NAME)) {
            return null;
        }

        if ($this->checker->hasMethod($node, self::METHOD_NAME)) {
            return null;
        }

        $queryParam = new Param(
            new Node\Expr\Variable('query'),
            null,
            new Identifier('string'),
        );

        $bindingsParam = new Param(
            new Node\Expr\Variable('bindings'),
            new Node\Expr\Array_(),
            new Identifier('array'),
        );

        $useReadPdoParam = new Param(
            new Node\Expr\Variable('useReadPdo'),
            new ConstFetch(new Name('true')),
            new Identifier('bool'),
        );

        $nop = new Nop();
        $nop->setAttribute('comments', [
            new Comment('// TODO: Implement scalar() method.'),
        ]);

        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new Identifier('mixed'),
            'params' => [$queryParam, $bindingsParam, $useReadPdoParam],
            'stmts' => [
                $nop,
                new Return_(new ConstFetch(new Name('null'))),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add scalar() method stub to ConnectionInterface implementations for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Database\ConnectionInterface;

class CustomConnection implements ConnectionInterface
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Database\ConnectionInterface;

class CustomConnection implements ConnectionInterface
{
    public function scalar(string $query, array $bindings = [], bool $useReadPdo = true): mixed
    {
        // TODO: Implement scalar() method.
        return null;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
