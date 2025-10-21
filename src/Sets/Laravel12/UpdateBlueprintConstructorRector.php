<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBlueprintConstructorRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        // Target statement nodes that can contain New_ expressions
        return [Expression::class, Return_::class];
    }

    public function refactor(Node $node): ?Node
    {
        $newExpr = null;

        // Extract the New_ expression from different statement types
        if ($node instanceof Expression && $node->expr instanceof Assign) {
            // Case: $blueprint = new Blueprint(...);
            if ($node->expr->expr instanceof New_) {
                $newExpr = $node->expr->expr;
            }
        } elseif ($node instanceof Return_) {
            // Case: return new Blueprint(...);
            if ($node->expr instanceof New_) {
                $newExpr = $node->expr;
            }
        } elseif ($node instanceof Expression && $node->expr instanceof New_) {
            // Case: new Blueprint(...); (expression statement)
            $newExpr = $node->expr;
        }

        if ($newExpr === null) {
            return null;
        }

        // Check if this is Blueprint instantiation
        if (!$newExpr->class instanceof Name) {
            return null;
        }

        $className = $this->getName($newExpr->class);
        if (
            $className !== "Blueprint" &&
            $className !== "Illuminate\Database\Schema\Blueprint"
        ) {
            return null;
        }

        // Check if Blueprint is being instantiated with the old signature
        // Old: new Blueprint($table, $callback, $prefix)
        // New: new Blueprint($connection, $table, $callback, $prefix)

        $argCount = count($newExpr->args);

        // Only add comment if using the old 3-argument signature
        if ($argCount === 3) {
            // Add a doc comment to the statement node
            // We cannot safely automate this as we don't know where to get the connection instance
            $node->setDocComment(new Doc(
                "/** Laravel 12: Blueprint constructor now requires a Connection instance as the FIRST parameter. " .
                    "Add the connection parameter manually: new Blueprint(\$connection, \$table, \$callback, \$prefix) */"
            ));
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update Blueprint constructor to include Connection instance parameter for Laravel 12",
            [
                new CodeSample(
                    'new Blueprint($table, $callback, $prefix)',
                    'new Blueprint($connection, $table, $callback, $prefix)',
                ),
            ],
        );
    }
}
