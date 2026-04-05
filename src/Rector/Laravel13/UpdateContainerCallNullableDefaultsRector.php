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
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateContainerCallNullableDefaultsRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 13: Container::call() now preserves nullable class defaults';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression || $this->hasComment($node)) {
            return null;
        }

        $call = $this->extractCall($node);

        if ($call === null || ! $this->isContainerCall($call) || ! $this->hasNullableClassDefaultCallback($call)) {
            return null;
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. Review any logic that expected an auto-resolved instance here.'),
        ], $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function extractCall(Expression $expression): MethodCall|StaticCall|null
    {
        if ($expression->expr instanceof MethodCall || $expression->expr instanceof StaticCall) {
            return $expression->expr;
        }

        if ($expression->expr instanceof Assign && ($expression->expr->expr instanceof MethodCall || $expression->expr->expr instanceof StaticCall)) {
            return $expression->expr->expr;
        }

        return null;
    }

    private function isContainerCall(MethodCall|StaticCall $call): bool
    {
        if (! $this->isName($call->name, 'call')) {
            return false;
        }

        if ($call instanceof StaticCall) {
            return $this->isName($call->class, 'Illuminate\\Container\\Container') || $this->isName($call->class, 'Container');
        }

        if ($this->isObjectType($call->var, new ObjectType('Illuminate\\Container\\Container'))) {
            return true;
        }

        if ($call->var instanceof Variable && is_string($call->var->name)) {
            return in_array($call->var->name, ['app', 'container'], true);
        }

        return false;
    }

    private function hasNullableClassDefaultCallback(MethodCall|StaticCall $call): bool
    {
        foreach ($call->args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($arg->value instanceof Closure && $this->closureHasNullableClassDefault($arg->value)) {
                return true;
            }
        }

        return false;
    }

    private function closureHasNullableClassDefault(Closure $closure): bool
    {
        foreach ($closure->params as $param) {
            if (! $param->type instanceof NullableType || ! $param->type->type instanceof Name || $param->default === null || ! $this->isName($param->default, 'null')) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function hasComment(Expression $expression): bool
    {
        foreach ($expression->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add an advisory comment when Container::call() uses nullable class defaults',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$container->call(function (?Carbon $date = null) {
    return $date;
});
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// Laravel 13: Container::call() now preserves nullable class defaults. Review any logic that expected an auto-resolved instance here.
$container->call(function (?Carbon $date = null) {
    return $date;
});
CODE_SAMPLE,
                ),
            ],
        );
    }
}
