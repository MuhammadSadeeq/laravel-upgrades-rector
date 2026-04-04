<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSchemaGetColumnTypeRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: Schema::getColumnType() now returns the actual column type';

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if ($this->containsSchemaGetColumnTypeCall($node) === false) {
            return null;
        }

        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return null;
            }
        }

        $node->setAttribute('comments', array_merge([
            new Comment('// ' . self::COMMENT_MARKER . ', not the Doctrine DBAL equivalent. Review any type comparisons.'),
        ], $node->getComments()));
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function containsSchemaGetColumnTypeCall(Expression $expression): bool
    {
        $containsCall = false;

        $this->traverseNodesWithCallable($expression->expr, function (Node $node) use (&$containsCall): ?Node {
            if ($node instanceof StaticCall && $node->class instanceof Name) {
                if ($this->isName($node->class, 'Illuminate\\Support\\Facades\\Schema') && $this->isName($node->name, 'getColumnType')) {
                    $containsCall = true;
                }
            }

            if ($node instanceof MethodCall) {
                if ($this->isObjectType($node->var, new ObjectType('Illuminate\\Database\\Schema\\Builder')) && $this->isName($node->name, 'getColumnType')) {
                    $containsCall = true;
                }
            }

            return null;
        });

        return $containsCall;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Warn when code depends on pre-Laravel 11 Schema::getColumnType() behavior',
            [
                new CodeSample(
                    '$type = Schema::getColumnType(\'users\', \'amount\');',
                    '// Laravel 11: Schema::getColumnType() now returns the actual column type, not the Doctrine DBAL equivalent. Review any type comparisons.
$type = Schema::getColumnType(\'users\', \'amount\');'
                ),
            ]
        );
    }
}
