<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Appends the dispatchAfterResponse() method to Illuminate\Contracts\Bus\Dispatcher
 * implementations. The interface declares dispatchAfterResponse($command,
 * $handler = null) with untyped parameters, so generated parameters stay
 * untyped and no return type is added.
 *
 * Existing methods are never modified: this rule only appends what is missing
 * (decision D7). chain() is not a Laravel 13 addition and is left alone.
 */
final class UpdateBusDispatcherContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Bus\Dispatcher';

    private const METHOD_NAME = 'dispatchAfterResponse';

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
                new Param(new Variable('command')),
                new Param(new Variable('handler'), new ConstFetch(new Name('null'))),
            ],
            'stmts' => [
                TodoNopFactory::create(TodoNopFactory::implementMessage('dispatchAfterResponse', 13)),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add dispatchAfterResponse() method to Bus Dispatcher contract implementations for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Bus\Dispatcher;

class CustomDispatcher implements Dispatcher
{
    public function dispatchAfterResponse($command, $handler = null)
    {
        // TODO: Laravel 13 — implement dispatchAfterResponse() to satisfy the updated contract.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
