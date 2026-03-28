<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReplaceHasVersion4UuidsRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Use_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Use_) {
            return null;
        }

        foreach ($node->uses as $use) {
            if (!$use instanceof UseItem) {
                continue;
            }

            $name = $use->name->toString();

            // Already using HasVersion4Uuids -- skip (idempotent)
            if ($name === 'Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids') {
                return null;
            }

            if ($name === 'Illuminate\Database\Eloquent\Concerns\HasUuids') {
                // To maintain UUIDv4 behavior, use HasVersion4Uuids with alias
                $use->name = new Name(
                    'Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids',
                );
                $use->alias = new Identifier('HasUuids');

                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Replace HasUuids import with HasVersion4Uuids (aliased as HasUuids) to preserve UUIDv4 behavior after Laravel 12 switched HasUuids to UUIDv7",
            [
                new CodeSample(
                    "use Illuminate\Database\Eloquent\Concerns\HasUuids;",
                    "use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;",
                ),
            ],
        );
    }
}
