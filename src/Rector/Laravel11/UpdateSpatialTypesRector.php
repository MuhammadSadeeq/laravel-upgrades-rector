<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSpatialTypesRector extends AbstractRector
{
    private const COMMENT_MARKER = 'Laravel 11: spatial types now use geometry() or geography()';

    /** @var array<int, string> */
    private const REMOVED_SPATIAL_METHODS = [
        'point',
        'lineString',
        'polygon',
        'geometryCollection',
        'multiPoint',
        'multiLineString',
        'multiPolygon',
        'multiPolygonZ',
    ];

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        $spatialMethodCall = $this->findSpatialMethodCallInChain($node->expr);

        if (! $spatialMethodCall instanceof MethodCall) {
            return null;
        }

        $methodName = $this->getName($spatialMethodCall->name);

        if ($methodName === null) {
            return null;
        }

        $spatialMethodCall->name = new Identifier('geometry');
        $spatialMethodCall->args[] = new Arg(
            new String_($methodName),
            false,
            false,
            [],
            new Identifier('subtype')
        );

        if (! $this->hasMigrationComment($node)) {
            $node->setAttribute('comments', array_merge([
                new Comment('// ' . self::COMMENT_MARKER . '. Review whether geometry() or geography() is the correct replacement for this column.'),
            ], $node->getComments()));
        }

        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);

        return $node;
    }

    private function findSpatialMethodCallInChain(Node $node): ?MethodCall
    {
        while ($node instanceof MethodCall) {
            if ($this->isLikelyBlueprint($node->var)) {
                $methodName = $this->getName($node->name);

                if ($methodName !== null && in_array($methodName, self::REMOVED_SPATIAL_METHODS, true)) {
                    return $node;
                }
            }

            $node = $node->var;
        }

        return null;
    }

    private function isLikelyBlueprint(Node $node): bool
    {
        if ($this->isObjectType($node, new ObjectType('Illuminate\\Database\\Schema\\Blueprint'))) {
            return true;
        }

        if (! $node instanceof Node\Expr\Variable) {
            return false;
        }

        return $this->isName($node, 'table') || $this->isName($node, 'blueprint');
    }

    private function hasMigrationComment(Expression $node): bool
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::COMMENT_MARKER)) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update spatial column types and warn that geometry() or geography() may be needed in Laravel 11',
            [
                new CodeSample(
                    '$table->point(\'coordinates\')',
                    '// Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
$table->geometry(\'coordinates\', subtype: \'point\')',
                ),
                new CodeSample(
                    '$table->polygon(\'area\')',
                    '// Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
$table->geometry(\'area\', subtype: \'polygon\')',
                ),
            ],
        );
    }
}
