<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSupportBehaviorChangesRector extends AbstractRector
{
    private const MANAGER_MARKER = 'Laravel 13: manager extend callbacks are now bound to the manager instance';

    private const SCHEDULING_MARKER = 'Laravel 13: withScheduling() registrations are now deferred';

    private const STR_MARKER = 'Laravel 13: custom Str factories are reset between tests';

    private const JS_MARKER = 'Laravel 13: Js::from() now uses unescaped Unicode by default';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        $expr = $this->extractExpressionNode($node);

        if ($expr instanceof MethodCall
            && $this->isName($expr->name, 'extend')
            && $this->isObjectType($expr->var, new ObjectType('Illuminate\\Support\\Manager'))
            && $this->hasThisUsingClosure($expr)
            && ! $this->hasComment($node, self::MANAGER_MARKER)
        ) {
            return $this->comment($node, self::MANAGER_MARKER . '. Capture dependencies with use (...) instead of relying on $this.');
        }

        if ($expr instanceof MethodCall
            && $this->isName($expr->name, 'withScheduling')
            && $this->isObjectType($expr->var, new ObjectType('Illuminate\\Foundation\\Configuration\\ApplicationBuilder'))
            && ! $this->hasComment($node, self::SCHEDULING_MARKER)
        ) {
            return $this->comment($node, self::SCHEDULING_MARKER . ' until Schedule is resolved. Review bootstrap logic that relied on immediate registration timing.');
        }

        if ($expr instanceof StaticCall && $this->isJsFromCall($expr) && ! $this->hasComment($node, self::JS_MARKER)) {
            return $this->comment($node, self::JS_MARKER . '. Update assertions that expected escaped Unicode sequences.');
        }

        if ($expr instanceof StaticCall && $this->isStrFactoryCall($expr) && str_contains($this->file->getFilePath(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) && ! $this->hasComment($node, self::STR_MARKER)) {
            return $this->comment($node, self::STR_MARKER . '. Re-register UUID / ULID / random-string factories in each relevant test or setup hook.');
        }

        return null;
    }

    private function extractExpressionNode(Expression $expression): Node
    {
        if ($expression->expr instanceof Assign) {
            return $expression->expr->expr;
        }

        return $expression->expr;
    }

    private function hasThisUsingClosure(MethodCall $methodCall): bool
    {
        foreach ($methodCall->args as $arg) {
            if (! $arg instanceof Arg || ! $arg->value instanceof Closure) {
                continue;
            }

            $usesThis = false;

            $this->traverseNodesWithCallable($arg->value->stmts ?? [], function (Node $node) use (&$usesThis): ?int {
                if ($node instanceof Variable && $node->name === 'this') {
                    $usesThis = true;
                }

                return null;
            });

            if ($usesThis) {
                return true;
            }
        }

        return false;
    }

    private function isJsFromCall(StaticCall $staticCall): bool
    {
        return $this->isName($staticCall->class, 'Js') || $this->isName($staticCall->class, 'Illuminate\\Support\\Js')
            ? $this->isName($staticCall->name, 'from')
            : false;
    }

    private function isStrFactoryCall(StaticCall $staticCall): bool
    {
        if (! $this->isName($staticCall->class, 'Str') && ! $this->isName($staticCall->class, 'Illuminate\\Support\\Str')) {
            return false;
        }

        $methodName = $this->getName($staticCall->name);

        if ($methodName === null) {
            return false;
        }

        return in_array($methodName, [
            'createRandomStringsUsing',
            'createRandomStringsNormally',
            'createUuidsUsing',
            'createUuidsNormally',
            'createUlidsUsing',
            'createUlidsNormally',
            'freezeUuids',
            'freezeUlids',
        ], true);
    }

    private function hasComment(Expression $expression, string $marker): bool
    {
        foreach ($expression->getComments() as $comment) {
            if (str_contains($comment->getText(), $marker)) {
                return true;
            }
        }

        return false;
    }

    private function comment(Expression $expression, string $commentText): Expression
    {
        $expression->setAttribute('comments', array_merge([
            new Comment('// ' . $commentText),
        ], $expression->getComments()));
        $expression->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $expression;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comments for Laravel 13 support-layer behavior changes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$manager->extend('custom', function () {
    return $this->app->make(CustomDriver::class);
});
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// Laravel 13: manager extend callbacks are now bound to the manager instance. Capture dependencies with use (...) instead of relying on $this.
$manager->extend('custom', function () {
    return $this->app->make(CustomDriver::class);
});
CODE_SAMPLE,
                ),
            ],
        );
    }
}
