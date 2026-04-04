<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateDatabaseSignatureChangesRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 12: database constructor and grammar APIs changed';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if ($this->hasUpgradeComment($node)) {
            return null;
        }

        $comment = $this->resolveComment($node);

        if ($comment === null) {
            return null;
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . $comment),
        ], $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function resolveComment(Expression $expression): ?string
    {
        $newExpression = $this->extractNewExpression($expression);

        if ($newExpression instanceof New_ && $this->isGrammarInstantiationWithoutConnection($newExpression)) {
            return self::COMMENT_MARKER . '. Grammar constructors now require a Connection instance, and setConnection() has been removed.';
        }

        if (! $expression->expr instanceof MethodCall) {
            return null;
        }

        $methodName = $this->getName($expression->expr->name);

        if ($methodName === null) {
            return null;
        }

        if ($methodName === 'setConnection' && $this->isObjectType($expression->expr->var, new ObjectType('Illuminate\\Database\\Grammar'))) {
            return self::COMMENT_MARKER . '. Grammar::setConnection() was removed; inject the Connection via the constructor instead.';
        }

        if ($methodName === 'withTablePrefix' && (
            $this->isObjectType($expression->expr->var, new ObjectType('Illuminate\\Database\\Connection')) ||
            $this->isObjectType($expression->expr->var, new ObjectType('Illuminate\\Database\\ConnectionInterface'))
        )) {
            return self::COMMENT_MARKER . '. Connection::withTablePrefix() was removed; read the prefix from the Connection directly instead.';
        }

        if ($methodName === 'getPrefix' && $this->isObjectType($expression->expr->var, new ObjectType('Illuminate\\Database\\Schema\\Blueprint'))) {
            return self::COMMENT_MARKER . '. Blueprint::getPrefix() is deprecated; retrieve the table prefix from the Connection instead.';
        }

        if (in_array($methodName, ['getTablePrefix', 'setTablePrefix'], true) && $this->isObjectType($expression->expr->var, new ObjectType('Illuminate\\Database\\Grammar'))) {
            return self::COMMENT_MARKER . '. Grammar::' . $methodName . '() is deprecated; retrieve the table prefix from the Connection instead.';
        }

        return null;
    }

    private function extractNewExpression(Expression $expression): ?New_
    {
        if ($expression->expr instanceof New_) {
            return $expression->expr;
        }

        if ($expression->expr instanceof Assign && $expression->expr->expr instanceof New_) {
            return $expression->expr->expr;
        }

        return null;
    }

    private function isGrammarInstantiationWithoutConnection(New_ $new): bool
    {
        if (! $new->class instanceof Name) {
            return false;
        }

        $className = $new->class->toString();

        if ($className === 'Illuminate\\Database\\Grammar') {
            return count($new->args) === 0;
        }

        return str_ends_with($className, 'Grammar') && str_contains($className, 'Illuminate\\Database\\') && count($new->args) === 0;
    }

    private function hasUpgradeComment(Expression $expression): bool
    {
        foreach ($expression->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add advisory comments for Laravel 12 database constructor and grammar API changes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$grammar = new MySqlGrammar();
$grammar->setConnection($connection);
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
// Laravel 12: database constructor and grammar APIs changed. Grammar constructors now require a Connection instance, and setConnection() has been removed.
$grammar = new MySqlGrammar();
// Laravel 12: database constructor and grammar APIs changed. Grammar::setConnection() was removed; inject the Connection via the constructor instead.
$grammar->setConnection($connection);
CODE_SAMPLE
                ),
            ]
        );
    }
}
