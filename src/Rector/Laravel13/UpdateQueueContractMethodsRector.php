<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateQueueContractMethodsRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Queue\Queue';

    /** @var array<string, array{returnType: string, nullable: bool, defaultReturn: string}> */
    private const METHODS = [
        'pendingSize' => ['returnType' => 'int', 'nullable' => false, 'defaultReturn' => 'int'],
        'delayedSize' => ['returnType' => 'int', 'nullable' => false, 'defaultReturn' => 'int'],
        'reservedSize' => ['returnType' => 'int', 'nullable' => false, 'defaultReturn' => 'int'],
        'creationTimeOfOldestPendingJob' => ['returnType' => 'int', 'nullable' => true, 'defaultReturn' => 'null'],
    ];

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

        $changed = false;

        foreach (self::METHODS as $methodName => $config) {
            if ($this->checker->hasMethod($node, $methodName)) {
                continue;
            }

            $returnType = $config['nullable']
                ? new NullableType(new Identifier($config['returnType']))
                : new Identifier($config['returnType']);

            $defaultReturn = $config['defaultReturn'] === 'null'
                ? new ConstFetch(new Name('null'))
                : new Int_(0);

            $method = new ClassMethod($methodName, [
                'flags' => Class_::MODIFIER_PUBLIC,
                'params' => [
                    new Param(
                        new Variable('queue'),
                        new ConstFetch(new Name('null')),
                        new NullableType(new Identifier('string')),
                    ),
                ],
                'returnType' => $returnType,
                'stmts' => [
                    new Return_($defaultReturn),
                ],
            ]);

            $node->stmts[] = $method;
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add missing queue sizing methods to Queue contract implementations for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Queue\Queue;

class CustomQueue implements Queue
{
    public function pendingSize(?string $queue = null): int
    {
        return 0;
    }
    public function delayedSize(?string $queue = null): int
    {
        return 0;
    }
    public function reservedSize(?string $queue = null): int
    {
        return 0;
    }
    public function creationTimeOfOldestPendingJob(?string $queue = null): ?int
    {
        return null;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
