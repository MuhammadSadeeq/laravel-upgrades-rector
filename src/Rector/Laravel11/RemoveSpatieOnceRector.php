<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\ImportUsageChecker;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Laravel 11 ships a native once() helper, so spatie/once is no longer needed.
 * Its imports are removed ONLY when nothing else in the file references them —
 * an import that is still used (e.g. Spatie\Once\Cache type hints) stays, and
 * the advisory engine reports it instead.
 */
final class RemoveSpatieOnceRector extends AbstractRector
{
    private ImportUsageChecker $importUsageChecker;

    public function __construct()
    {
        $this->importUsageChecker = new ImportUsageChecker;
    }

    public function getNodeTypes(): array
    {
        return [Use_::class];
    }

    /**
     * @return int|Node|null
     */
    public function refactor(Node $node)
    {
        if (! $node instanceof Use_) {
            return null;
        }

        $keptUseItems = [];
        $removedCount = 0;

        foreach ($node->uses as $use) {
            $fqcn = $use->name->toString();

            if (! str_starts_with($fqcn, 'Spatie\\Once\\') && $fqcn !== 'Spatie\\Once') {
                $keptUseItems[] = $use;

                continue;
            }

            $alias = $use->getAlias()->name;
            $used = $this->importUsageChecker->isUsed(
                $this->file->getNewStmts(),
                $this->file->getOriginalFileContent(),
                $fqcn,
                $alias
            );

            if ($used) {
                $keptUseItems[] = $use;

                continue;
            }

            $removedCount++;
        }

        if ($removedCount === 0) {
            return null;
        }

        if ($keptUseItems === []) {
            return NodeTraverser::REMOVE_NODE;
        }

        $node->uses = $keptUseItems;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove unused Spatie\Once imports (Laravel 11 provides a native once() helper)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Spatie\Once\Cache;

$result = once(function () {
    return expensive_operation();
});
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$result = once(function () {
    return expensive_operation();
});
CODE_SAMPLE,
                ),
            ],
        );
    }
}
