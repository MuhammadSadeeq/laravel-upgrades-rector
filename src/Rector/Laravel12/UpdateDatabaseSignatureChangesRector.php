<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel12;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\CommentInserter;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateDatabaseSignatureChangesRector extends AbstractRector
{
    private const COMMENT_MARKER = '@laravel-upgrade db-signature-changes';

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

        $message = $this->resolveCommentMessage($node);

        if ($message === null) {
            return null;
        }

        if (! $this->commentInserter->addComment($node, self::COMMENT_MARKER, $message)) {
            return null;
        }

        return $node;
    }

    private function resolveCommentMessage(Expression $expression): ?string
    {
        $newExpression = $this->extractNewExpression($expression);

        if ($newExpression instanceof New_ && $this->isGrammarInstantiationWithoutConnection($newExpression)) {
            return 'Grammar constructors now require a Connection instance, and setConnection() has been removed.';
        }

        $message = null;

        $this->traverseNodesWithCallable($expression->expr, function (Node $node) use (&$message): ?int {
            if ($message !== null) {
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            if ($node instanceof New_ && $this->isGrammarInstantiationWithoutConnection($node)) {
                $message = 'Grammar constructors now require a Connection instance, and setConnection() has been removed.';

                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            if (! $node instanceof MethodCall) {
                return null;
            }

            $message = $this->resolveMethodCallMessage($node);

            return $message === null ? null : NodeTraverser::DONT_TRAVERSE_CHILDREN;
        });

        return $message;
    }

    private function resolveMethodCallMessage(MethodCall $methodCall): ?string
    {
        $methodName = $this->getName($methodCall->name);

        if ($methodName === null) {
            return null;
        }

        if ($methodName === 'setConnection' && $this->isLikelyGrammar($methodCall->var)) {
            return 'Grammar::setConnection() was removed; inject the Connection via the constructor instead.';
        }

        if ($methodName === 'withTablePrefix' && $this->isLikelyConnection($methodCall->var)) {
            return 'Connection::withTablePrefix() was removed; read the prefix from the Connection directly instead.';
        }

        if ($methodName === 'getPrefix' && $this->isLikelyBlueprint($methodCall->var)) {
            return 'Blueprint::getPrefix() is deprecated; retrieve the table prefix from the Connection instead.';
        }

        if (in_array($methodName, ['getTablePrefix', 'setTablePrefix'], true) && $this->isLikelyGrammar($methodCall->var)) {
            return 'Grammar::'.$methodName.'() is deprecated; retrieve the table prefix from the Connection instead.';
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

        $className = $this->getName($new->class) ?? $new->class->toString();

        if ($className === 'Illuminate\\Database\\Grammar') {
            return count($new->args) === 0;
        }

        return str_ends_with($className, 'Grammar') && str_contains($className, 'Illuminate\\Database\\') && count($new->args) === 0;
    }

    private function isLikelyGrammar(Node $node): bool
    {
        if ($this->isObjectType($node, new ObjectType('Illuminate\\Database\\Grammar'))) {
            return true;
        }

        return $node instanceof Variable && is_string($node->name) && str_contains(strtolower($node->name), 'grammar');
    }

    private function isLikelyConnection(Node $node): bool
    {
        if ($this->isObjectType($node, new ObjectType('Illuminate\\Database\\Connection'))
            || $this->isObjectType($node, new ObjectType('Illuminate\\Database\\ConnectionInterface'))) {
            return true;
        }

        return $node instanceof Variable && is_string($node->name) && in_array(strtolower($node->name), ['connection', 'db'], true);
    }

    private function isLikelyBlueprint(Node $node): bool
    {
        if ($this->isObjectType($node, new ObjectType('Illuminate\\Database\\Schema\\Blueprint'))) {
            return true;
        }

        return $node instanceof Variable && is_string($node->name) && in_array(strtolower($node->name), ['blueprint', 'table'], true);
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
