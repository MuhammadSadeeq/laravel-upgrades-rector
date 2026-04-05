<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateDatabaseQueryBehaviorRector extends AbstractRector
{
    private const UPSERT_MARKER = 'Laravel 13: upsert() now throws InvalidArgumentException when uniqueBy is empty';

    private const DELETE_MARKER = 'Laravel 13: MySQL joined delete queries now compile ORDER BY / LIMIT clauses';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        $call = $this->extractMethodCall($node);

        if (! $call instanceof MethodCall) {
            return null;
        }

        if ($this->isName($call->name, 'upsert') && $this->hasEmptyUniqueBy($call) && ! $this->hasComment($node, self::UPSERT_MARKER)) {
            $node->setAttribute('comments', array_merge([
                new Comment('// ' . self::UPSERT_MARKER . ' on MySQL or MariaDB. Pass a non-empty uniqueBy value.'),
            ], $node->getComments()));
            $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

            return $node;
        }

        if ($this->isName($call->name, 'delete') && $this->isDeleteChainWithJoinAndOrderOrLimit($call) && ! $this->hasComment($node, self::DELETE_MARKER)) {
            $node->setAttribute('comments', array_merge([
                new Comment('// ' . self::DELETE_MARKER . '. Review this delete() call if it targets MySQL or MariaDB.'),
            ], $node->getComments()));
            $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

            return $node;
        }

        return null;
    }

    private function extractMethodCall(Expression $expression): ?MethodCall
    {
        if ($expression->expr instanceof MethodCall) {
            return $expression->expr;
        }

        if ($expression->expr instanceof Assign && $expression->expr->expr instanceof MethodCall) {
            return $expression->expr->expr;
        }

        return null;
    }

    private function hasEmptyUniqueBy(MethodCall $call): bool
    {
        if (! isset($call->args[1]) || ! $call->args[1] instanceof Arg) {
            return false;
        }

        $value = $call->args[1]->value;

        if ($value instanceof Array_) {
            return $value->items === [];
        }

        return $value instanceof String_ && $value->value === '';
    }

    private function isDeleteChainWithJoinAndOrderOrLimit(MethodCall $call): bool
    {
        $hasJoin = false;
        $hasOrderOrLimit = false;
        $currentNode = $call->var;

        while ($currentNode instanceof MethodCall) {
            $methodName = $this->getName($currentNode->name);

            if ($methodName !== null) {
                if (str_contains($methodName, 'join')) {
                    $hasJoin = true;
                }

                if (in_array($methodName, ['orderBy', 'orderByDesc', 'limit'], true)) {
                    $hasOrderOrLimit = true;
                }
            }

            $currentNode = $currentNode->var;
        }

        return $hasJoin && $hasOrderOrLimit;
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

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comments for Laravel 13 database upsert and joined delete behavior changes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$query->upsert($rows, [], ['name']);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// Laravel 13: upsert() now throws InvalidArgumentException when uniqueBy is empty on MySQL or MariaDB. Pass a non-empty uniqueBy value.
$query->upsert($rows, [], ['name']);
CODE_SAMPLE,
                ),
            ],
        );
    }
}
