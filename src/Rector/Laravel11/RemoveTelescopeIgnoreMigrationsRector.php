<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Telescope 5 removed the ignoreMigrations() method. Removes the call
 * entirely — migrations are always loaded by default in Telescope 5.
 */
final class RemoveTelescopeIgnoreMigrationsRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?int
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof StaticCall) {
            return null;
        }

        $staticCall = $node->expr;

        if (! $staticCall->name instanceof Identifier) {
            return null;
        }

        if ($staticCall->name->toLowerString() !== 'ignoremigrations') {
            return null;
        }

        if (! $staticCall->class instanceof Name) {
            return null;
        }

        $raw = ltrim($staticCall->class->toString(), '\\');

        if (! in_array($raw, ['Telescope', 'Laravel\Telescope\Telescope'], true)) {
            return null;
        }

        // Remove the entire expression statement.
        return NodeVisitor::REMOVE_NODE;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Telescope::ignoreMigrations() calls (removed in Telescope 5)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Telescope::ignoreMigrations();
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
CODE_SAMPLE
                ),
            ],
        );
    }
}
