<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\NodeVisitor;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateHttpClientThrowSignaturesRector extends AbstractRector
{
    private const ADVISORY_COMMENT = '// Laravel 13: The throw()/throwIf() method signatures have changed. Update: public function throw($callback = null) and public function throwIf($condition, $callback = null)';

    private const TARGET_PARENT_CLASS = 'Illuminate\Http\Client\Response';

    /** @var string[] */
    private const TARGET_METHODS = ['throw', 'throwIf'];

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_) {
            return null;
        }

        if (!$this->extendsTargetClass($node)) {
            return null;
        }

        $hasChanges = false;

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod) {
                continue;
            }

            $methodName = $this->getName($stmt->name);

            if ($methodName === null || !in_array($methodName, self::TARGET_METHODS, true)) {
                continue;
            }

            if ($this->hasAdvisoryComment($stmt)) {
                continue;
            }

            $existingComments = $stmt->getComments();
            $newComment = new Comment(self::ADVISORY_COMMENT);
            $stmt->setAttribute('comments', array_merge([$newComment], $existingComments));
            $stmt->setAttribute(AttributeKey::ORIGINAL_NODE, null);
            $hasChanges = true;
        }

        return $hasChanges ? $node : null;
    }

    private function extendsTargetClass(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentName = $node->extends->toString();

        if ($parentName === self::TARGET_PARENT_CLASS) {
            return true;
        }

        return $parentName === 'Response' && $this->fileHasImport(self::TARGET_PARENT_CLASS);
    }

    private function fileHasImport(string $fullyQualifiedName): bool
    {
        $hasImport = false;

        $this->traverseNodesWithCallable($this->file->getNewStmts(), function (Node $node) use ($fullyQualifiedName, &$hasImport): ?int {
            if (! $node instanceof Use_) {
                return null;
            }

            foreach ($node->uses as $use) {
                if (! $use instanceof UseItem) {
                    continue;
                }

                if ($use->name->toString() === $fullyQualifiedName) {
                    $hasImport = true;
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }
            }

            return null;
        });

        return $hasImport;
    }

    private function hasAdvisoryComment(ClassMethod $node): bool
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), 'Laravel 13:')) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comment for HTTP Client throw()/throwIf() method signature changes in Laravel 13',
            [
                new CodeSample(
                    'class CustomResponse extends Response {
    public function throw() { /* ... */ }
}',
                    'class CustomResponse extends Response {
    // Laravel 13: The throw()/throwIf() method signatures have changed. Update: public function throw($callback = null) and public function throwIf($condition, $callback = null)
    public function throw() { /* ... */ }
}'
                ),
            ]
        );
    }
}
