<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateUserProviderContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Auth\UserProvider';

    private const METHOD_NAME = 'rehashPasswordIfRequired';

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

        $userParam = new Param(
            new Node\Expr\Variable('user'),
            null,
            new FullyQualified('Illuminate\Contracts\Auth\Authenticatable'),
        );

        $credentialsParam = new Param(
            new Node\Expr\Variable('credentials'),
            null,
            new Identifier('array'),
        );

        $forceParam = new Param(
            new Node\Expr\Variable('force'),
            new ConstFetch(new Name('false')),
            new Identifier('bool'),
        );

        $nop = new Nop();
        $nop->setAttribute('comments', [
            new Comment('// TODO: Implement rehashPasswordIfRequired() method.'),
        ]);

        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new Identifier('void'),
            'params' => [$userParam, $credentialsParam, $forceParam],
            'stmts' => [
                $nop,
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add rehashPasswordIfRequired() method stub to UserProvider implementations for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Auth\UserProvider;

class CustomUserProvider implements UserProvider
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Auth\UserProvider;

class CustomUserProvider implements UserProvider
{
    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false): void
    {
        // TODO: Implement rehashPasswordIfRequired() method.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
