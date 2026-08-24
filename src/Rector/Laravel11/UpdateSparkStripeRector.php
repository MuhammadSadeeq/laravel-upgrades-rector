<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSparkStripeRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11 Spark Stripe 5:';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof StaticCall) {
            return null;
        }

        if (! $node->expr->class instanceof Name) {
            return null;
        }

        if (! $this->isName($node->expr->class, 'Laravel\\Spark\\Spark')) {
            return null;
        }

        if (! $this->isName($node->expr->name, 'ignoreMigrations')) {
            return null;
        }

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// '.self::COMMENT_MARKER.' Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=spark-migrations'),
        ], $node->getComments()));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add an informational comment for Laravel Spark Stripe 5 migration publishing in Laravel 11',
            [
                new CodeSample(
                    'Spark::ignoreMigrations();',
                    <<<'CODE_SAMPLE'
// Laravel 11 Spark Stripe 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=spark-migrations
Spark::ignoreMigrations();
CODE_SAMPLE
                ),
            ]
        );
    }
}
