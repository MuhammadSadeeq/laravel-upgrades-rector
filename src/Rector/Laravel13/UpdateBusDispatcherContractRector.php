<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBusDispatcherContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Bus\Dispatcher';

    private const METHOD_NAME = 'dispatchAfterResponse';

    private const CHAIN_METHOD_NAME = 'chain';

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

        $hasChanged = false;

        $dispatchAfterResponseMethod = $this->findLocalMethod($node, self::METHOD_NAME);
        if ($dispatchAfterResponseMethod instanceof ClassMethod) {
            $this->configureDispatchAfterResponseMethod($dispatchAfterResponseMethod);
            $hasChanged = true;
        } elseif (!$this->checker->hasMethod($node, self::METHOD_NAME)) {
            $node->stmts[] = $this->createDispatchAfterResponseMethod();
            $hasChanged = true;
        }

        $chainMethod = $this->findLocalMethod($node, self::CHAIN_METHOD_NAME);
        if ($chainMethod instanceof ClassMethod) {
            $this->configureChainMethod($chainMethod);
            $hasChanged = true;
        } elseif (!$this->checker->hasMethod($node, self::CHAIN_METHOD_NAME)) {
            $node->stmts[] = $this->createChainMethod();
            $hasChanged = true;
        }

        return $hasChanged ? $node : null;
    }

    private function createDispatchAfterResponseMethod(): ClassMethod
    {
        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'stmts' => [
                $this->createTodoNop(self::METHOD_NAME),
            ],
        ]);

        $this->configureDispatchAfterResponseMethod($method);

        return $method;
    }

    private function createChainMethod(): ClassMethod
    {
        $method = new ClassMethod(self::CHAIN_METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'stmts' => [
                $this->createTodoNop(self::CHAIN_METHOD_NAME),
            ],
        ]);

        $this->configureChainMethod($method);

        return $method;
    }

    private function configureDispatchAfterResponseMethod(ClassMethod $method): void
    {
        $method->params = [
            new Param(new Variable('command'), null, new Identifier('mixed')),
            new Param(new Variable('handler'), new ConstFetch(new Name('null')), new Identifier('mixed')),
        ];
        $method->returnType = new Identifier('void');
        $method->stmts = $this->removeVoidReturnValues($method->stmts ?? []);
    }

    private function configureChainMethod(ClassMethod $method): void
    {
        $method->params = [
            new Param(
                new Variable('jobs'),
                new ConstFetch(new Name('null')),
                new UnionType([
                    new FullyQualified('Illuminate\Support\Collection'),
                    new Identifier('array'),
                    new Name('null'),
                ]),
            ),
        ];
        $method->returnType = new Identifier('mixed');
    }

    /**
     * @param Node\Stmt[] $stmts
     * @return Node\Stmt[]
     */
    private function removeVoidReturnValues(array $stmts): array
    {
        $newStmts = [];

        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Return_) {
                $newStmts[] = $stmt;

                continue;
            }

            if ($stmt->expr instanceof Node\Expr) {
                $newStmts[] = new Expression($stmt->expr);
            }
        }

        if ($newStmts === []) {
            return [
                $this->createTodoNop(self::METHOD_NAME),
            ];
        }

        return $newStmts;
    }

    private function createTodoNop(string $methodName): Nop
    {
        $nop = new Nop();
        $nop->setAttribute('comments', [
            new Comment(sprintf('// TODO: Implement %s() method.', $methodName)),
        ]);

        return $nop;
    }

    private function findLocalMethod(Class_ $class, string $methodName): ?ClassMethod
    {
        foreach ($class->getMethods() as $classMethod) {
            if ($this->isName($classMethod, $methodName)) {
                return $classMethod;
            }
        }

        return null;
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
    public function dispatchAfterResponse(mixed $command, mixed $handler = null): void
    {
        // TODO: Implement dispatchAfterResponse() method.
    }

    public function chain(\Illuminate\Support\Collection|array|null $jobs = null): mixed
    {
        // TODO: Implement chain() method.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
