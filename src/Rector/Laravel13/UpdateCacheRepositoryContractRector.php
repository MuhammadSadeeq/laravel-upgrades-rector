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
 * Appends the touch() method added to Illuminate\Contracts\Cache\Repository
 * in Laravel 13. The interface declares `touch($key, $ttl)` with untyped
 * parameters and no return type.
 */
final class UpdateCacheRepositoryContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Cache\Repository';

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

        $node->stmts[] = new ClassMethod(self::METHOD_NAME, [
            'flags' => Modifiers::PUBLIC,
            'params' => [
                new Param(new Variable('key')),
                new Param(new Variable('ttl')),
            ],
            'stmts' => [
                TodoNopFactory::create(TodoNopFactory::implementMessage('touch', 13)),
            ],
        ]);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add touch() method to Cache Repository contract implementations for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Cache\Repository;

class CustomRepository implements Repository
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Cache\Repository;

class CustomRepository implements Repository
{
    public function touch($key, $ttl)
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
