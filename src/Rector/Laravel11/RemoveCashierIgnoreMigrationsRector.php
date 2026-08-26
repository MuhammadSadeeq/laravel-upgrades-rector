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
 * Cashier 15 removed the ignoreMigrations() method from the Cashier class.
 * Removes the call entirely — migrations are always loaded by default.
 */
final class RemoveCashierIgnoreMigrationsRector extends AbstractRector
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

        if (! in_array($raw, ['Cashier', 'Laravel\Cashier\Cashier'], true)) {
            return null;
        }

        return NodeVisitor::REMOVE_NODE;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Cashier::ignoreMigrations() calls (removed in Cashier 15)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
Cashier::ignoreMigrations();
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
CODE_SAMPLE
                ),
            ],
        );
    }
}
