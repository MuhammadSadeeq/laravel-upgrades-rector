<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBatchRepositoryInterfaceRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Bus\BatchRepository';

    private const METHOD_NAME = 'rollBack';

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
            // rollBack() is declared with no return type in the contract;
            // adding ": void" is covariant-legal.
            'returnType' => new Identifier('void'),
            'stmts' => [
                TodoNopFactory::create(TodoNopFactory::implementMessage('rollBack', 11)),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add rollBack() method stub to BatchRepository implementations for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Bus\BatchRepository;

class CustomBatchRepository implements BatchRepository
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Bus\BatchRepository;

class CustomBatchRepository implements BatchRepository
{
    public function rollBack(): void
    {
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
