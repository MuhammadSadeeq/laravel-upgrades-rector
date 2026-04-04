<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateAuthenticationExceptionRedirectToRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: AuthenticationException::redirectTo() now requires the current Request instance';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $this->containsRedirectToCallWithoutRequest($node)) {
            return null;
        }

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. Pass $request to redirectTo().'),
        ], $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function containsRedirectToCallWithoutRequest(Expression $expression): bool
    {
        $containsCall = false;

        $this->traverseNodesWithCallable($expression->expr, function (Node $node) use (&$containsCall): ?Node {
            if (! $node instanceof Node\Expr\MethodCall) {
                return null;
            }

            if (! $this->isName($node->name, 'redirectTo')) {
                return null;
            }

            if ($node->args !== []) {
                return null;
            }

            if (! $this->isObjectType($node->var, new ObjectType('Illuminate\\Auth\\AuthenticationException'))) {
                return null;
            }

            $containsCall = true;

            return null;
        });

        return $containsCall;
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
