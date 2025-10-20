<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
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
            if (!$use instanceof UseUse) {
                continue;
            }

            $name = $use->name->toString();

            if ($name === "Illuminate\Database\Eloquent\Concerns\HasUuids") {
                // To maintain UUIDv4 behavior, use HasVersion4Uuids with alias
                $use->name = new Name(
                    "Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids",
                );
                $use->alias = new \PhpParser\Node\Identifier("HasUuids");

                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Replace HasVersion4Uuids with HasUuids and add alias for Laravel 12 compatibility",
            [
                new CodeSample(
                    "use Illuminate\Database\Eloquent\Concerns\HasUuids;",
                    "use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;",
                ),
            ],
        );
    }
}
