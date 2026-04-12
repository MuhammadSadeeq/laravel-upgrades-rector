<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\UnionType;
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

        $method = $this->findLocalMethod($node, self::METHOD_NAME);
        if ($method instanceof ClassMethod) {
            $this->configureEventStreamMethod($method);

            return $node;
        }

        if ($this->checker->hasMethod($node, self::METHOD_NAME)) {
            return null;
        }

        $node->stmts[] = $this->createEventStreamMethod();

        return $node;
    }

    private function createEventStreamMethod(): ClassMethod
    {
        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'stmts' => [
                $this->createTodoNop('eventStream'),
            ],
        ]);

        $this->configureEventStreamMethod($method);

        return $method;
    }

    private function configureEventStreamMethod(ClassMethod $method): void
    {
        $method->params = [
            new Param(new Variable('callback'), null, new FullyQualified('Closure')),
            new Param(new Variable('headers'), new Array_([]), new Identifier('array')),
            new Param(
                new Variable('endStreamWith'),
                new String_('</stream>'),
                new UnionType([
                    new FullyQualified('Illuminate\Http\StreamedEvent'),
                    new Identifier('string'),
                    new Name('null'),
                ]),
            ),
        ];
        $method->returnType = new FullyQualified('Symfony\Component\HttpFoundation\StreamedResponse');
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
    public function eventStream(\Closure $callback, array $headers = [], \Illuminate\Http\StreamedEvent|string|null $endStreamWith = '</stream>'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // TODO: Implement eventStream() method.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
