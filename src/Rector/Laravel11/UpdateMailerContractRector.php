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
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateMailerContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Mail\Mailer';

    private const METHOD_NAME = 'sendNow';

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

        $mailableParam = new Param(
            new Node\Expr\Variable('mailable'),
        );

        $dataParam = new Param(
            new Node\Expr\Variable('data'),
            new Node\Expr\Array_(),
            new Identifier('array'),
        );

        $callbackParam = new Param(
            new Node\Expr\Variable('callback'),
            new ConstFetch(new Name('null')),
        );

        $nop = new Nop();
        $nop->setAttribute('comments', [
            new Comment('// TODO: Implement sendNow() method.'),
        ]);

        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new NullableType(new FullyQualified('Illuminate\\Contracts\\Mail\\SentMessage')),
            'params' => [$mailableParam, $dataParam, $callbackParam],
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
            'Add sendNow() method stub to Mailer implementations for Laravel 11',
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
    public function sendNow($mailable, array $data = [], $callback = null): void
    {
        // TODO: Implement sendNow() method.
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
