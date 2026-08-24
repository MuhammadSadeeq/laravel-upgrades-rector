<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateMariaDbUuidColumnRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: the new mariadb driver creates native UUID columns for uuid()';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof MethodCall) {
            return null;
        }

        if (! $this->isName($node->expr->name, 'uuid')) {
            return null;
        }

        if (! $this->isObjectType($node->expr->var, new ObjectType('Illuminate\\Database\\Schema\\Blueprint'))) {
            return null;
        }

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// '.self::COMMENT_MARKER.' Use char(..., 36) instead if you switch to the mariadb driver and need the previous behavior'),
        ], $node->getComments()));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn on uuid() columns that may change behavior when adopting Laravel 11\'s dedicated MariaDB driver',
            [
                new CodeSample(
                    '$table->uuid(\'uuid\');',
                    '// Laravel 11: the new mariadb driver creates native UUID columns for uuid(). Use char(..., 36) instead if you switch to the mariadb driver and need the previous behavior.
$table->uuid(\'uuid\');'
                ),
            ]
        );
    }
}
