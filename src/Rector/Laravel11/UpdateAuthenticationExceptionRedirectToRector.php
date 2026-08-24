<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateAuthenticationExceptionRedirectToRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade auth-redirect-to';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class, Node\Expr\Closure::class, Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        $requestVariableName = $this->resolveRequestVariableName($node);

        if ($requestVariableName !== null && $this->addRequestArgumentToCalls($node, $requestVariableName)) {
            return $node;
        }

        if (! $node instanceof Expression) {
            return null;
        }

        if (! $this->containsRedirectToCallWithoutRequest($node)) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'AuthenticationException::redirectTo() now requires the current Request instance. Pass $request to redirectTo().'
        )) {
            return null;
        }

        return $node;
    }

    private function resolveRequestVariableName(Node $node): ?string
    {
        if (! $node instanceof ClassMethod && ! $node instanceof Function_ && ! $node instanceof Node\Expr\Closure) {
            return null;
        }

        foreach ($node->params as $param) {
            if (! $param->var instanceof Node\Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            if ($param->var->name === 'request') {
                return $param->var->name;
            }

            if ($param->type instanceof Node\Name && $this->isName($param->type, 'Illuminate\\Http\\Request')) {
                return $param->var->name;
            }
        }

        return null;
    }

    private function addRequestArgumentToCalls(Node $node, string $requestVariableName): bool
    {
        $hasChanged = false;

        $this->traverseNodesWithCallable($node, function (Node $node) use (&$hasChanged, $requestVariableName): ?Node {
            if (! $node instanceof Node\Expr\MethodCall) {
                return null;
            }

            if (! $this->isRedirectToCallWithoutRequest($node)) {
                return null;
            }

            $node->args[] = new Node\Arg(new Node\Expr\Variable($requestVariableName));
            $hasChanged = true;

            return $node;
        });

        return $hasChanged;
    }

    private function containsRedirectToCallWithoutRequest(Expression $expression): bool
    {
        $containsCall = false;

        $this->traverseNodesWithCallable($expression->expr, function (Node $node) use (&$containsCall): ?Node {
            if (! $node instanceof Node\Expr\MethodCall || ! $this->isRedirectToCallWithoutRequest($node)) {
                return null;
            }

            $containsCall = true;

            return null;
        });

        return $containsCall;
    }

    private function isRedirectToCallWithoutRequest(Node\Expr\MethodCall $methodCall): bool
    {
        if (! $this->isName($methodCall->name, 'redirectTo')) {
            return false;
        }

        if ($methodCall->args !== []) {
            return false;
        }

        return $this->isAuthenticationExceptionExpression($methodCall->var);
    }

    private function isAuthenticationExceptionExpression(Node $node): bool
    {
        if ($this->isObjectType($node, new ObjectType('Illuminate\\Auth\\AuthenticationException'))) {
            return true;
        }

        if (! $node instanceof Node\Expr\Variable || ! is_string($node->name)) {
            return false;
        }

        return in_array($node->name, ['e', 'exception', 'authException', 'authenticationException'], true);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn when AuthenticationException::redirectTo() is called without a Request in Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
if ($e instanceof AuthenticationException) {
    $path = $e->redirectTo();
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
if ($e instanceof AuthenticationException) {
    // Laravel 11: AuthenticationException::redirectTo() now requires the current Request instance. Pass $request to redirectTo().
    $path = $e->redirectTo();
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
