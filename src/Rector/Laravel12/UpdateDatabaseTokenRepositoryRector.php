<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Node;
use PhpParser\Node\Arg;
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
        if (! $node instanceof New_) {
            return null;
        }

        if (! $node->class instanceof Name) {
            return null;
        }

        if (
            ! $this->isName($node->class, 'DatabaseTokenRepository') &&
            ! $this->isName($node->class, 'Illuminate\Auth\Passwords\DatabaseTokenRepository')
        ) {
            return null;
        }

        if (count($node->args) < 5) {
            return null;
        }

        $expiresArg = $node->args[4];

        if (! $expiresArg instanceof Arg) {
            return null;
        }

        if ($expiresArg->value instanceof Mul) {
            return null;
        }

        if (! $expiresArg->value instanceof LNumber) {
            return null;
        }

        $minutes = $expiresArg->value->value;

        $expiresArg->value = new Mul(
            new LNumber($minutes),
            new LNumber(60),
        );

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update DatabaseTokenRepository constructor to convert expires from minutes to seconds for Laravel 12',
            [
                new CodeSample(
                    'new DatabaseTokenRepository($connection, $hasher, $table, $key, 60);',
                    'new DatabaseTokenRepository($connection, $hasher, $table, $key, 60 * 60);',
                ),
            ],
        );
    }
}
