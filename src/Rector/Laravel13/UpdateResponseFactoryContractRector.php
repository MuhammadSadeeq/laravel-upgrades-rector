<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateResponseFactoryContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Routing\ResponseFactory';

    private const METHOD_NAME = 'eventStream';

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

        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param(new Variable('callback'), null, new Identifier('callable')),
                new Param(
                    new Variable('endStream'),
                    new ConstFetch(new Name('null')),
                    new NullableType(new Identifier('string')),
                ),
                new Param(new Variable('headers'), new Array_([]), new Identifier('array')),
            ],
            'returnType' => new Identifier('mixed'),
            'stmts' => [],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add eventStream() method to ResponseFactory contract implementations for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Routing\ResponseFactory;

class CustomResponseFactory implements ResponseFactory
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Routing\ResponseFactory;

class CustomResponseFactory implements ResponseFactory
{
    public function eventStream(callable $callback, ?string $endStream = null, array $headers = [])
    {
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
