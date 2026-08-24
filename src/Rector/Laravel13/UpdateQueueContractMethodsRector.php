<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Appends the queue sizing methods added to Illuminate\Contracts\Queue\Queue
 * in Laravel 13: pendingSize(), delayedSize(), reservedSize() and
 * creationTimeOfOldestPendingJob().
 *
 * The interface declares them all as `($queue = null)` with untyped
 * parameters, so generated parameters stay untyped (a typed parameter would
 * fatal). The native return types are covariant-legal additions.
 */
final class UpdateQueueContractMethodsRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Queue\Queue';

    /**
     * @var array<string, string>
     */
    private const METHODS = [
        'pendingSize' => 'int',
        'delayedSize' => 'int',
        'reservedSize' => 'int',
        'creationTimeOfOldestPendingJob' => '?int',
    ];

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

        $changed = false;

        foreach (self::METHODS as $methodName => $returnType) {
            if ($this->checker->hasMethod($node, $methodName)) {
                continue;
            }

            $returnExpr = $methodName === 'creationTimeOfOldestPendingJob'
                ? new ConstFetch(new Name('null'))
                : new Node\Scalar\LNumber(0);

            $method = new ClassMethod($methodName, [
                'flags' => Modifiers::PUBLIC,
                'params' => [
                    new Param(new Variable('queue'), new ConstFetch(new Name('null'))),
                ],
                'returnType' => str_starts_with($returnType, '?')
                    ? new NullableType(new Identifier(substr($returnType, 1)))
                    : new Identifier($returnType),
                'stmts' => [
                    TodoNopFactory::create(TodoNopFactory::implementMessage($methodName, 13)),
                    new Return_($returnExpr),
                ],
            ]);

            $node->stmts[] = $method;
            $changed = true;
        }

        if (! $changed) {
            return null;
        }

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add queue sizing methods to Queue contract implementations for Laravel 13',
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
    public function pendingSize($queue = null): int
    {
        // TODO: Laravel 13 — implement pendingSize() to satisfy the updated contract.
        return 0;
    }

    public function delayedSize($queue = null): int
    {
        // TODO: Laravel 13 — implement delayedSize() to satisfy the updated contract.
        return 0;
    }

    public function reservedSize($queue = null): int
    {
        // TODO: Laravel 13 — implement reservedSize() to satisfy the updated contract.
        return 0;
    }

    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        // TODO: Laravel 13 — implement creationTimeOfOldestPendingJob() to satisfy the updated contract.
        return null;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
