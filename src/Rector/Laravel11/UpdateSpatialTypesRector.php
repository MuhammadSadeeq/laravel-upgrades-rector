<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer\BlueprintReceiverResolver;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Laravel 11 removed the individual spatial column helpers in favour of
 * geometry($column, $subtype = null, $srid = 0) / geography(...).
 *
 * The rewrite keeps the verified positional argument order — the previous
 * version appended a named `subtype:` argument, which made the srid land in
 * the subtype slot and produced a fatal "named parameter overwrites
 * previous argument" migration.
 *
 * - point('loc')            → geometry('loc', 'point')
 * - point('geo', 4326)      → geometry('geo', 'point', 4326)
 * - multiPolygonZ(...) etc. → untouched (no geometry() equivalent; reported)
 */
final class UpdateSpatialTypesRector extends AbstractRector
{
    /**
     * camelCase helper name => lowercase geometry subtype.
     *
     * @var array<string, string>
     */
    private const SPATIAL_METHODS = [
        'point' => 'point',
        'lineString' => 'linestring',
        'polygon' => 'polygon',
        'geometryCollection' => 'geometrycollection',
        'multiPoint' => 'multipoint',
        'multiLineString' => 'multilinestring',
        'multiPolygon' => 'multipolygon',
    ];

    private BlueprintReceiverResolver $blueprintReceiverResolver;

    public function __construct()
    {
        $this->blueprintReceiverResolver = new BlueprintReceiverResolver;
    }

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

        if ($methodName === null || ! isset(self::SPATIAL_METHODS[$methodName])) {
            return null;
        }

        // Rewrite to geometry($column, '<subtype>', ...rest) positionally.
        $spatialMethodCall->name = new Identifier('geometry');

        $args = [];

        foreach ($spatialMethodCall->getArgs() as $arg) {
            if ($arg instanceof Arg) {
                $args[] = $arg;
            }
        }

        /** @var Arg|null $columnArg */
        $columnArg = array_shift($args);

        if ($columnArg === null) {
            return null;
        }

        $subtypeArg = new Arg(new String_(self::SPATIAL_METHODS[$methodName]));

        $spatialMethodCall->args = array_merge([$columnArg, $subtypeArg], $args);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rewrite removed spatial column helpers to geometry()/geography() calls in Laravel 11 migrations',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$table->point('location');
$table->point('geo', 4326);
$table->multiPolygonZ('areas');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$table->geometry('location', 'point');
$table->geometry('geo', 'point', 4326);
$table->multiPolygonZ('areas');
CODE_SAMPLE,
                ),
            ],
        );
    }

    private function findSpatialMethodCallInChain(Node $node): ?MethodCall
    {
        while ($node instanceof MethodCall) {
            if ($this->blueprintReceiverResolver->isBlueprint($node->var)) {
                $methodName = $this->getName($node->name);

                if ($methodName !== null && isset(self::SPATIAL_METHODS[$methodName])) {
                    return $node;
                }
            }

            $node = $node->var;
        }

        return null;
    }
}
