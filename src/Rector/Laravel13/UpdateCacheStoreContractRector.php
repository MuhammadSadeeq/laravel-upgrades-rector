<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Appends the touch() method added to Illuminate\Contracts\Cache\Store in
 * Laravel 13. The interface declares `touch($key, $seconds)` with untyped
 * parameters and no return type, so generated parameters stay untyped.
 */
final class UpdateCacheStoreContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Cache\Store';

    private const METHOD_NAME = 'touch';

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
            'params' => [
                new Param(new Variable('key')),
                new Param(new Variable('seconds')),
            ],
            'stmts' => [
                TodoNopFactory::create(TodoNopFactory::implementMessage('touch', 13)),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add touch() method to Cache Store contract implementations for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Cache\Store;

class CustomStore implements Store
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Cache\Store;

class CustomStore implements Store
{
    public function touch($key, $seconds)
    {
        // TODO: Laravel 13 — implement touch() to satisfy the updated contract.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
