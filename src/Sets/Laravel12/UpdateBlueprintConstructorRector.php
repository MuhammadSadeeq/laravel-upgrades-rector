<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateBlueprintConstructorRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof New_) {
            return null;
        }

        // Check if this is Blueprint instantiation
        if (!$node->class instanceof Name) {
            return null;
        }

        $className = $this->getName($node->class);
        if (
            $className !== "Blueprint" &&
            $className !== "Illuminate\Database\Schema\Blueprint"
        ) {
            return null;
        }

        // Check if Blueprint is being instantiated with the old signature
        // Old: new Blueprint($table, $callback, $prefix)
        // New: new Blueprint($connection, $table, $callback, $prefix)

        $argCount = count($node->args);

        // If we have 3 arguments (old signature), we need to add connection as first parameter
        if ($argCount === 3) {
            // Add a connection parameter as first argument - we'll use a variable that should be available in context
            array_unshift($node->args, new Arg(new Variable("connection")));
            return $node;
        }

        // Add a comment indicating manual review needed
        $node->setAttribute("comments", [
            new \PhpParser\Comment\Doc(
                "/** Laravel 12: Blueprint constructor now requires a Connection instance as the FIRST parameter. " .
                    "Please review and add the connection parameter manually. */",
            ),
        ]);
        return $node;
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
