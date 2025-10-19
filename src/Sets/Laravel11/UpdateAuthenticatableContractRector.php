<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateAuthenticatableContractRector extends AbstractRector
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

        // Check if this class implements Authenticatable contract
        $implementsAuthenticatable = false;
        if ($node->implements) {
            foreach ($node->implements as $implement) {
                if (
                    $this->isName(
                        $implement,
                        "Illuminate\Contracts\Auth\Authenticatable",
                    )
                ) {
                    $implementsAuthenticatable = true;
                    break;
                }
            }
        }

        if (!$implementsAuthenticatable) {
            return null;
        }

        // Check if the class already has the getAuthPasswordName method
        $hasPasswordNameMethod = false;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "getAuthPasswordName")
            ) {
                $hasPasswordNameMethod = true;
                break;
            }
        }

        // If it doesn't have the method, add documentation
        if (!$hasPasswordNameMethod) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: Authenticatable contract requires new getAuthPasswordName method. " .
                        'Add: public function getAuthPasswordName() { return \'password\'; } */',
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for missing getAuthPasswordName method in Authenticatable implementations for Laravel 11",
            [
                new CodeSample(
                    'class User implements Authenticatable
{
    // existing methods...
}',
                    '/** Laravel 11: Authenticatable contract requires new getAuthPasswordName method. Add: public function getAuthPasswordName() { return \'password\'; } */
class User implements Authenticatable
{
    // existing methods...
}',
                ),
            ],
        );
    }
}
