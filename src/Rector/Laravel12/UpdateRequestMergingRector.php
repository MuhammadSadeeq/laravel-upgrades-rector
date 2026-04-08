<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateRequestMergingRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Expression) {
            return null;
        }

        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        if (!$this->isName($node->expr->name, 'mergeIfMissing')) {
            return null;
        }

        if (! $this->isRequestLikeCall($node->expr)) {
            return null;
        }

        $existingComments = $node->getComments();
        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 12:')) {
                return null;
            }
        }

        $newComment = new Comment(
            '// Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.'
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function isRequestLikeCall(MethodCall $methodCall): bool
    {
        if ($methodCall->var instanceof Variable && $this->isName($methodCall->var, 'request')) {
            return true;
        }

        if (! $methodCall->var instanceof Variable || ! $this->isName($methodCall->var, 'this')) {
            return false;
        }

        return $this->fileContainsFormRequestSubclass();
    }

    private function fileHasImport(string $fullyQualifiedName): bool
    {
        foreach ($this->file->getNewStmts() as $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $use) {
                if ($use->name->toString() === $fullyQualifiedName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function fileContainsFormRequestSubclass(): bool
    {
        $hasFormRequestSubclass = false;

        $this->traverseNodesWithCallable($this->file->getNewStmts(), function (Node $node) use (&$hasFormRequestSubclass): ?int {
            if (! $node instanceof Class_ || ! $node->extends instanceof Name) {
                return null;
            }

            if ($this->isName($node->extends, 'Illuminate\\Foundation\\Http\\FormRequest')) {
                $hasFormRequestSubclass = true;

                return 1;
            }

            if ($this->isName($node->extends, 'FormRequest') && $this->fileHasImport('Illuminate\\Foundation\\Http\\FormRequest')) {
                $hasFormRequestSubclass = true;

                return 1;
            }

            return null;
        });

        return $hasFormRequestSubclass;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for Request mergeIfMissing() nested array merging behavior change in Laravel 12',
            [
                new CodeSample(
                    '$request->mergeIfMissing($data);',
                    '// Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
$request->mergeIfMissing($data);'
                ),
            ]
        );
    }
}
