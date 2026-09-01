<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateAuthenticationExceptionRedirectToRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class, Node\Expr\Closure::class];
    }

    public function refactor(Node $node): ?Node
    {
        $requestVariableName = $this->resolveRequestVariableName($node);

        if ($requestVariableName !== null && $this->addRequestArgumentToCalls($node, $requestVariableName)) {
            return $node;
        }

        // Calls without a request parameter are intentionally left unchanged;
        // guessing a request variable would corrupt code.
        return null;
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
        return $this->isObjectType($node, new ObjectType('Illuminate\\Auth\\AuthenticationException'));
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Pass the current Request to AuthenticationException::redirectTo() in Laravel 11',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function handle(AuthenticationException $e, Request $request): string
{
    return $e->redirectTo();
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function handle(AuthenticationException $e, Request $request): string
{
    return $e->redirectTo($request);
}
CODE_SAMPLE
                ),
            ]
        );
    }
}
