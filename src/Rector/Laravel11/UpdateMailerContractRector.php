<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Appends the sendNow() method added to Illuminate\Contracts\Mail\Mailer in
 * Laravel 11. The interface declares no native return type but documents
 * "@return \Illuminate\Mail\SentMessage|null", so the concrete class
 * Illuminate\Mail\SentMessage is used for a covariant-legal native type.
 */
final class UpdateMailerContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Mail\Mailer';

    private const METHOD_NAME = 'sendNow';

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
            'returnType' => new NullableType(new FullyQualified('Illuminate\Mail\SentMessage')),
            'params' => [
                new Param(new Variable('mailable')),
                new Param(new Variable('data'), new Array_([]), new Identifier('array')),
                new Param(new Variable('callback'), new ConstFetch(new Name('null'))),
            ],
            'stmts' => [
                TodoNopFactory::create(TodoNopFactory::implementMessage('sendNow', 11)),
                new Return_(new ConstFetch(new Name('null'))),
            ],
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add sendNow() method to Mailer contract implementations for Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Mail\Mailer;

class CustomMailer implements Mailer
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Mail\Mailer;

class CustomMailer implements Mailer
{
    public function sendNow($mailable, array $data = [], $callback = null): ?\Illuminate\Mail\SentMessage
    {
        // TODO: Laravel 11 — implement sendNow() to satisfy the updated contract.
        return null;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
