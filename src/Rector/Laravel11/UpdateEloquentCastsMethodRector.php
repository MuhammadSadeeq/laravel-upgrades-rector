<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateEloquentCastsMethodRector extends AbstractRector
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

        // Check if this class extends Model or has Eloquent traits
        $isEloquentModel = false;

        // Check if class extends Model
        if (
            $node->extends &&
            $this->isName($node->extends, "Illuminate\Database\Eloquent\Model")
        ) {
            $isEloquentModel = true;
        }

        // Check for common Eloquent model patterns
        if (
            $node->name &&
            (str_ends_with($node->name->name, "Model") ||
                in_array($node->name->name, ["User", "Post", "Product"], true))
        ) {
            $isEloquentModel = true;
        }

        if (!$isEloquentModel) {
            return null;
        }

        // Check if the class has a casts method
        $hasCastsMethod = false;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "casts")
            ) {
                $hasCastsMethod = true;
                break;
            }
        }

        // If it has a casts method, add a comment about potential conflicts
        if ($hasCastsMethod) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: Base Eloquent model now defines a casts() method. " .
                        "If this class has a casts relationship method, it may conflict. " .
                        "Consider renaming the relationship method to avoid conflicts. */",
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for potential casts method conflicts in Eloquent models for Laravel 11",
            [
                new CodeSample(
                    'class User extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }
}',
                    '/** Laravel 11: Base Eloquent model now defines a casts() method. If this class has a casts relationship method, it may conflict. Consider renaming the relationship method to avoid conflicts. */
class User extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }
}',
                ),
            ],
        );
    }
}
