<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateDatabaseConnectionInterfaceRector extends AbstractRector
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

        // Check if this class implements ConnectionInterface
        $implementsConnectionInterface = false;
        if ($node->implements) {
            foreach ($node->implements as $implement) {
                if (
                    $this->isName(
                        $implement,
                        "Illuminate\Database\ConnectionInterface",
                    )
                ) {
                    $implementsConnectionInterface = true;
                    break;
                }
            }
        }

        if (!$implementsConnectionInterface) {
            return null;
        }

        // Check if the class already has the scalar method
        $hasScalarMethod = false;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "scalar")
            ) {
                $hasScalarMethod = true;
                break;
            }
        }

        // If it doesn't have the method, add documentation
        if (!$hasScalarMethod) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: ConnectionInterface requires new scalar method. " .
                        'Add: public function scalar($query, $bindings = [], $useReadPdo = true); */',
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for missing scalar method in ConnectionInterface implementations for Laravel 11",
            [
                new CodeSample(
                    'class CustomConnection implements ConnectionInterface
{
    // existing methods...
}',
                    '/** Laravel 11: ConnectionInterface requires new scalar method. Add: public function scalar($query, $bindings = [], $useReadPdo = true); */
class CustomConnection implements ConnectionInterface
{
    // existing methods...
}',
                ),
            ],
        );
    }
}
