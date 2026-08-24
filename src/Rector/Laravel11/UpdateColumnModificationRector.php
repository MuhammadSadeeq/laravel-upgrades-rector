<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateColumnModificationRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade column-change';

    public function __construct(
        private readonly CommentInserter $commentInserter,
    ) {}

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

        if (! $this->isName($node->expr->name, 'change')) {
            return null;
        }

        if (! $this->isInBlueprintMethodChain($node->expr)) {
            return null;
        }

        if (! $this->commentInserter->addComment(
            $node,
            self::COMMENT_MARKER,
            'change() now requires all column modifiers to be explicitly re-specified. Review this migration.'
        )) {
            return null;
        }

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
                    '$table->integer(\'votes\')->nullable()->change();',
                    '// Laravel 11: change() now requires all column modifiers to be explicitly re-specified. Review this migration.
$table->integer(\'votes\')->nullable()->change();',
                ),
            ],
        );
    }
}
