<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Mul;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateDatabaseTokenRepositoryRector extends AbstractRector
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

        // Check if this is DatabaseTokenRepository instantiation
        if (!$node->class instanceof Name) {
            return null;
        }

        $className = $this->getName($node->class);
        if (
            $className !== "DatabaseTokenRepository" &&
            $className !== "Illuminate\Auth\Passwords\DatabaseTokenRepository"
        ) {
            return null;
        }

        // Check if there's an expires parameter (typically the 5th argument)
        if (count($node->args) < 5) {
            return null;
        }

        $expiresArg = $node->args[4];

        // If the expires value is a number that looks like minutes (typically 60, 90, 120)
        // convert to seconds by multiplying by 60
        if ($expiresArg instanceof \PhpParser\Node\Arg && $expiresArg->value instanceof LNumber) {
            $minutes = $expiresArg->value->value;

            // Common minute values that should be converted to seconds
            if (
                in_array(
                    $minutes,
                    [15, 30, 60, 90, 120, 180, 240, 360, 720, 1440],
                    true,
                )
            ) {
                $expiresArg->value = new Mul(
                    new LNumber($minutes),
                    new LNumber(60),
                );
                return $node;
            }
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update DatabaseTokenRepository constructor to expect expires in seconds instead of minutes for Laravel 12",
            [
                new CodeSample(
                    'new DatabaseTokenRepository($connection, $hasher, $table, $key, 60)',
                    'new DatabaseTokenRepository($connection, $hasher, $table, $key, 60 * 60)',
                ),
            ],
        );
    }
}
