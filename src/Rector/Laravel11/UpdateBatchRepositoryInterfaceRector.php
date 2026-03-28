<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBatchRepositoryInterfaceRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_) {
            return null;
        }

        // Check if this class implements BatchRepository interface
        $implementsBatchRepository = false;
        if ($node->implements) {
            foreach ($node->implements as $implement) {
                if (
                    $this->isName($implement, "Illuminate\Bus\BatchRepository")
                ) {
                    $implementsBatchRepository = true;
                    break;
                }
            }
        }

        if (!$implementsBatchRepository) {
            return null;
        }

        // Check if the class already has the rollBack method
        $hasRollBackMethod = false;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "rollBack")
            ) {
                $hasRollBackMethod = true;
                break;
            }
        }

        // If it doesn't have the method, add documentation
        if (!$hasRollBackMethod) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: BatchRepository interface requires new rollBack method. " .
                        "Add: public function rollBack(); */",
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for missing rollBack method in BatchRepository implementations for Laravel 11",
            [
                new CodeSample(
                    'class CustomBatchRepository implements BatchRepository
{
    // existing methods...
}',
                    '/** Laravel 11: BatchRepository interface requires new rollBack method. Add: public function rollBack(); */
class CustomBatchRepository implements BatchRepository
{
    // existing methods...
}',
                ),
            ],
        );
    }
}
