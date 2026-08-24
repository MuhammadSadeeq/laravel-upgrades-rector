<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReplaceHasVersion4UuidsRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 12: HasUuids now generates UUIDv7';

    public function getNodeTypes(): array
    {
        return [Use_::class, TraitUse::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Use_) {
            return $this->refactorUseStatement($node);
        }

        if (! $node instanceof TraitUse) {
            return null;
        }

        foreach ($node->traits as $trait) {
            if ($this->isName($trait, 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids')
                && ! $this->fileHasImport('Illuminate\\Database\\Eloquent\\Concerns\\HasUuids')) {
                return $this->addComment($node);
            }
        }

        return null;
    }

    private function refactorUseStatement(Use_ $node): ?Node
    {
        foreach ($node->uses as $use) {
            if ($use->name->toString() === 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids') {
                return $this->addComment($node);
            }
        }

        return null;
    }

    private function addComment(Node $node): ?Node
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// '.self::COMMENT_MARKER.'. Switch to HasVersion4Uuids if you need the previous ordered UUIDv4 behavior.'),
        ], $node->getComments()));

        return $node;
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
            'Add an advisory comment when HasUuids may need to be replaced with HasVersion4Uuids to preserve UUIDv4 behavior',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Database\Eloquent\Concerns\HasUuids;
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 12: HasUuids now generates UUIDv7. Switch to HasVersion4Uuids if you need the previous ordered UUIDv4 behavior.
use Illuminate\Database\Eloquent\Concerns\HasUuids;
CODE_SAMPLE
                ),
            ],
        );
    }
}
