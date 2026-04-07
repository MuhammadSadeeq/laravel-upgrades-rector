<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateColumnModificationRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Expression) {
            return null;
        }

        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        if (!$this->isName($node->expr->name, 'change')) {
            return null;
        }

        if (!$this->isInBlueprintMethodChain($node->expr)) {
            return null;
        }

        $existingComments = $node->getComments();

        foreach ($existingComments as $comment) {
            if (str_contains($comment->getText(), 'Laravel 11:')) {
                return null;
            }
        }

        $newComment = new Comment(
            '// Laravel 11: change() now requires all column modifiers to be explicitly re-specified. Review this migration.'
        );

        $node->setAttribute('comments', array_merge([$newComment], $existingComments));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    /**
     * Walk down the method chain to find if the root call is on $table.
     */
    private function isInBlueprintMethodChain(MethodCall $node): bool
    {
        $var = $node->var;

        while ($var instanceof MethodCall) {
            $var = $var->var;
        }

        if (! $var instanceof Variable) {
            return false;
        }

        if ($this->isObjectType($var, new ObjectType('Illuminate\\Database\\Schema\\Blueprint'))) {
            return true;
        }

        return $this->isName($var, 'table');
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add documentation comments for column modification behavior changes in Laravel 11',
            [
                new CodeSample(
                    '$table->integer(\'votes\')->nullable()->change()',
                    '// Laravel 11: change() now requires all column modifiers to be explicitly re-specified. Review this migration.
$table->integer(\'votes\')->nullable()->change()',
                ),
            ],
        );
    }
}
