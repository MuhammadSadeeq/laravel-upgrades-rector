<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReplaceHasVersion7UuidsRector extends AbstractRector
{
    private const HAS_UUIDS = 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids';

    private const HAS_VERSION_7_UUIDS = 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids';

    public function getNodeTypes(): array
    {
        return [Use_::class, Class_::class];
    }

    /**
     * @return int|Node|null
     */
    public function refactor(Node $node)
    {
        if ($node instanceof Use_) {
            return $this->refactorUseStatement($node);
        }

        if ($node instanceof Class_) {
            return $this->refactorClass($node);
        }

        return null;
    }

    /**
     * @return int|Node|null
     */
    private function refactorUseStatement(Use_ $node)
    {
        $changed = false;
        $hasHasUuidsImport = $this->fileHasImport(self::HAS_UUIDS);
        $uses = [];

        foreach ($node->uses as $use) {
            if ($use->name->toString() !== self::HAS_VERSION_7_UUIDS) {
                $uses[] = $use;

                continue;
            }

            $changed = true;

            if ($hasHasUuidsImport && $use->alias === null) {
                continue;
            }

            $use->name = new Name(self::HAS_UUIDS);
            $uses[] = $use;
        }

        if (! $changed) {
            return null;
        }

        if ($uses === []) {
            return NodeTraverser::REMOVE_NODE;
        }

        $node->uses = $uses;

        return $node;
    }

    private function refactorClass(Class_ $node): ?Node
    {
        $changed = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $index => $trait) {
                if ($this->isName($trait, self::HAS_VERSION_7_UUIDS)) {
                    if ($this->fileHasImport(self::HAS_VERSION_7_UUIDS)
                        || $this->fileHasImport(self::HAS_UUIDS)) {
                        $stmt->traits[$index] = new Name('HasUuids');
                    } else {
                        $stmt->traits[$index] = new Name\FullyQualified(self::HAS_UUIDS);
                    }
                    $changed = true;
                }
            }
        }

        return $changed ? $node : null;
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
            'Replace HasVersion7Uuids import with HasUuids since Laravel 12 switched HasUuids to UUIDv7 by default',
            [
                new CodeSample(
                    'use Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids;',
                    'use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;',
                ),
            ],
        );
    }
}
