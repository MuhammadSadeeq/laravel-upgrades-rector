<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Param;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateUserProviderContractRector extends AbstractRector
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

        // Check if this class implements UserProvider contract
        $implementsUserProvider = false;
        if ($node->implements) {
            foreach ($node->implements as $implement) {
                if (
                    $this->isName(
                        $implement,
                        "Illuminate\Contracts\Auth\UserProvider",
                    )
                ) {
                    $implementsUserProvider = true;
                    break;
                }
            }
        }

        if (!$implementsUserProvider) {
            return null;
        }

        // Check if the class already has the rehashPasswordIfRequired method
        $hasRehashMethod = false;
        foreach ($node->stmts as $stmt) {
            if (
                $stmt instanceof ClassMethod &&
                $this->isName($stmt->name, "rehashPasswordIfRequired")
            ) {
                $hasRehashMethod = true;
                break;
            }
        }

        // If it doesn't have the method, add documentation
        if (!$hasRehashMethod) {
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: UserProvider contract requires new rehashPasswordIfRequired method. " .
                        'Add: public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false); */',
                ),
            ]);
            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Add documentation for missing rehashPasswordIfRequired method in UserProvider implementations for Laravel 11",
            [
                new CodeSample(
                    'class CustomUserProvider implements UserProvider
{
    // existing methods...
}',
                    '/** Laravel 11: UserProvider contract requires new rehashPasswordIfRequired method. Add: public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false); */
class CustomUserProvider implements UserProvider
{
    // existing methods...
}',
                ),
            ],
        );
    }
}
