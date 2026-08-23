<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\InterfaceImplementationChecker;
use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\TodoNopFactory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateMustVerifyEmailContractRector extends AbstractRector
{
    private const INTERFACE_NAME = 'Illuminate\Contracts\Auth\MustVerifyEmail';

    private const METHOD_NAME = 'markEmailAsUnverified';

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

        // Eloquent models get a working body via forceFill(); anything else
        // gets an explicit TODO so the silent `return false` lie is gone.
        $stmts = $this->isEloquentModel($node)
            ? [
                TodoNopFactory::create('Laravel 13 — verify the cleared attribute matches your verification flow.'),
                new Expression(new MethodCall(new MethodCall(
                    new Variable('this'),
                    'forceFill',
                    [new Arg(new Array_([new ArrayItem(
                        new ConstFetch(new Name('null')),
                        new String_('email_verified_at')
                    )]))]
                ), 'save')),
                new Return_(new ConstFetch(new Name('true'))),
            ]
            : [
                TodoNopFactory::create(TodoNopFactory::implementMessage('markEmailAsUnverified', 13)),
                new Return_(new ConstFetch(new Name('false'))),
            ];

        $method = new ClassMethod(self::METHOD_NAME, [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new Identifier('bool'),
            'stmts' => $stmts,
        ]);

        $node->stmts[] = $method;

        return $node;
    }

    private function isEloquentModel(Class_ $class): bool
    {
        $scope = $class->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return false;
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection) {
            return false;
        }

        return $classReflection->is('Illuminate\Database\Eloquent\Model')
            || $classReflection->isSubclassOf('Illuminate\Database\Eloquent\Model');
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add markEmailAsUnverified() method to MustVerifyEmail contract implementations for Laravel 13',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
    public function markEmailAsUnverified(): bool
    {
        // TODO: Laravel 13 — verify the cleared attribute matches your verification flow.
        $this->forceFill(['email_verified_at' => null])->save();

        return true;
    }
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
