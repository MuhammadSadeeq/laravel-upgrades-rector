<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateAuthenticatableContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Auth\Authenticatable';

    private const METHOD_NAME = 'getAuthPasswordName';

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
            'returnType' => new Identifier('string'),
            'stmts' => [
                TodoNopFactory::create(
                    'Laravel 11 — adjust this default when the password credential column is not "password".'
                ),
                new Return_(new String_('password')),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add getAuthPasswordName() method stub to Authenticatable implementations for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Auth\Authenticatable;

class CustomAuth implements Authenticatable
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Auth\Authenticatable;

class CustomAuth implements Authenticatable
{
    public function getAuthPasswordName(): string
    {
        return 'password';
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
