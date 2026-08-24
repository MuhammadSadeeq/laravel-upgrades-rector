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

final class UpdateTelescopeRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11 Telescope 5:';

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

        if (! $this->isName($node->expr->class, 'Laravel\\Telescope\\Telescope')) {
            return null;
        }

        $existingComments = $node->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// '.self::COMMENT_MARKER.' Migrations no longer auto-loaded. '
            .'Run: php artisan vendor:publish --tag=telescope-migrations'
        );
        $node->setAttribute('comments', array_merge([$newComment], $existingComments));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add informational comment about Telescope 5.0 migration publishing requirement',
            [
                new CodeSample(
                    'Telescope::filter(function ($entry) { });',
                    <<<'CODE_SAMPLE'
// Laravel 11 Telescope 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=telescope-migrations
Telescope::filter(function ($entry) { });
CODE_SAMPLE
                ),
            ]
        );
    }
}
