<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Appends the scalar() method added to Illuminate\Database\ConnectionInterface
 * in Laravel 11. The signature mirrors the interface exactly: the parameters
 * are untyped there, so adding native parameter types here would be a fatal
 * "Declaration must be compatible" error.
 */
final class UpdateDatabaseConnectionInterfaceRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Database\ConnectionInterface';

    private const METHOD_NAME = 'scalar';

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

        if ($this->checker->hasMethod($node, self::METHOD_NAME)) {
            return null;
        }

        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Modifiers::PUBLIC,
            // The interface declares no return type; adding ": mixed" is
            // covariant-legal and documents the intent from its docblock.
            'returnType' => new Identifier('mixed'),
            'params' => [
                new Param(new Variable('query')),
                new Param(new Variable('bindings'), new Array_([])),
                new Param(new Variable('useReadPdo'), new ConstFetch(new Name('true'))),
            ],
            'stmts' => [
                TodoNopFactory::create(TodoNopFactory::implementMessage('scalar', 11)),
                new Return_(new ConstFetch(new Name('null'))),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add scalar() method to ConnectionInterface implementations for Laravel 11',
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
    public function scalar($query, $bindings = [], $useReadPdo = true): mixed
    {
        // TODO: Laravel 11 — implement scalar() to satisfy the updated contract.
        return null;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
