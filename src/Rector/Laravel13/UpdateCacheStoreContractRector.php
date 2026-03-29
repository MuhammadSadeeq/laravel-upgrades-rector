<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateCacheStoreContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Cache\Store';

    private const METHOD_NAME = 'touch';

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
                new Param(new Variable('key'), null, new Identifier('string')),
                new Param(new Variable('seconds'), null, new Identifier('int')),
            ],
            'returnType' => new Identifier('bool'),
            'stmts' => [
                new Return_(new ConstFetch(new Name('false'))),
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
    public function touch(string $key, int $seconds): bool
    {
        return false;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
