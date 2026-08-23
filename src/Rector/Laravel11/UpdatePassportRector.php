<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdatePassportRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11 Passport 12:';

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

        if (! $this->isName($node->expr->class, 'Laravel\\Passport\\Passport')) {
            return null;
        }

        $methodName = $this->getName($node->expr->name);

        if ($methodName === null) {
            return null;
        }

        if ($methodName === 'enablePasswordGrant') {
            return null;
        }

        $existingComments = $node->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $newComment = new Comment(
            '// ' . self::COMMENT_MARKER . ' Password grant disabled by default. '
            . 'Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations'
        );
        $node->setAttribute('comments', array_merge([$newComment], $existingComments));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add informational comments for Passport static calls about Laravel 11 changes',
            [
                new CodeSample(
                    'Passport::routes()',
                    <<<'CODE_SAMPLE'
// Laravel 11 Passport 12: Password grant disabled by default. Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations
Passport::routes();
CODE_SAMPLE
                ),
            ]
        );
    }
}
