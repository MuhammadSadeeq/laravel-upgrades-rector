<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateNotificationBehaviorRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 13: queued notifications now respect DeleteWhenMissingModels';

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_ || $this->hasComment($node)) {
            return null;
        }

        if (! $this->extendsNotification($node) || ! $this->implementsShouldQueue($node)) {
            return null;
        }

        if (! $this->hasDeleteWhenMissingModelsHint($node)) {
            return null;
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . '. Verify this notification should be deleted instead of failing when related models are missing.'),
        ], $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function extendsNotification(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        $extendsName = $class->extends->toString();

        if ($extendsName === 'Illuminate\\Notifications\\Notification') {
            return true;
        }

        return $extendsName === 'Notification' && $this->fileHasImport('Illuminate\\Notifications\\Notification');
    }

    private function hasDeleteWhenMissingModelsHint(Class_ $class): bool
    {
        foreach ($class->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeName = $attribute->name->toString();

                if (in_array($attributeName, ['DeleteWhenMissingModels', 'Illuminate\\Queue\\Attributes\\DeleteWhenMissingModels'], true)) {
                    return true;
                }
            }
        }

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $property) {
                if ($property->name->name === 'deleteWhenMissingModels') {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasComment(Class_ $class): bool
    {
        foreach ($class->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    private function implementsShouldQueue(Class_ $class): bool
    {
        foreach ($class->implements as $implement) {
            $implementName = $implement->toString();

            if ($implementName === 'Illuminate\\Contracts\\Queue\\ShouldQueue') {
                return true;
            }

            if ($implementName === 'ShouldQueue' && $this->fileHasImport('Illuminate\\Contracts\\Queue\\ShouldQueue')) {
                return true;
            }
        }

        return false;
    }

    private function fileHasImport(string $fullyQualifiedName): bool
    {
        $hasImport = false;

        $this->traverseNodesWithCallable($this->file->getNewStmts(), function (Node $node) use ($fullyQualifiedName, &$hasImport): ?int {
            if (! $node instanceof Use_) {
                return null;
            }

            foreach ($node->uses as $use) {
                if ($use->name->toString() === $fullyQualifiedName) {
                    $hasImport = true;
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }

            return null;
        });

        return $hasImport;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add an advisory comment when queued notifications opt into DeleteWhenMissingModels behavior',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
#[DeleteWhenMissingModels]
class ResetNotification extends Notification implements ShouldQueue
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// Laravel 13: queued notifications now respect DeleteWhenMissingModels. Verify this notification should be deleted instead of failing when related models are missing.
#[DeleteWhenMissingModels]
class ResetNotification extends Notification implements ShouldQueue
{
}
CODE_SAMPLE,
                ),
            ],
        );
    }
}
