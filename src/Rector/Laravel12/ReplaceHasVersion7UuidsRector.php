<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReplaceHasVersion7UuidsRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Use_::class, Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Use_) {
            return $this->refactorUseStatement($node);
        }

        if ($node instanceof Class_) {
            return $this->refactorClass($node);
        }

        return null;
    }

    private function refactorUseStatement(Use_ $node): ?Node
    {
        foreach ($node->uses as $use) {
            if (!$use instanceof UseItem) {
                continue;
            }

            $name = $use->name->toString();

            if ($name === 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids') {
                return null;
            }

            if ($name === 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids') {
                $use->name = new Name(
                    'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids',
                );

                return $node;
            }
        }

        return null;
    }

    private function refactorClass(Class_ $node): ?Node
    {
        $changed = false;

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $index => $trait) {
                if ($this->isName($trait, 'HasVersion7Uuids')
                    || $this->isName($trait, 'Illuminate\\Database\\Eloquent\\Concerns\\HasVersion7Uuids')) {
                    $stmt->traits[$index] = new Name('HasUuids');
                    $changed = true;
                }
            }
        }

        return $changed ? $node : null;
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
