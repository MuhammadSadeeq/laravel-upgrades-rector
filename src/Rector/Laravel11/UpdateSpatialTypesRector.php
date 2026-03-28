<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSpatialTypesRector extends AbstractRector
{
    /** @var array<int, string> */
    private array $removedSpatialMethods = [
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
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        if (!$this->isObjectType($node->var, new ObjectType('Illuminate\Database\Schema\Blueprint'))) {
            return null;
        }

        $methodName = $this->getName($node->name);

        if ($methodName === null) {
            return null;
        }

        if (!in_array($methodName, $this->removedSpatialMethods, true)) {
            return null;
        }

        // Replace with geometry method
        $node->name = new Identifier('geometry');

        // Add subtype parameter as a named argument
        $node->args[] = new Arg(
            new String_($methodName),
            false,
            false,
            [],
            new Identifier('subtype')
        );

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Update spatial column types to use geometry/geography methods with subtype parameter for Laravel 11',
            [
                new CodeSample(
                    '$table->point(\'coordinates\')',
                    '$table->geometry(\'coordinates\', subtype: \'point\')',
                ),
                new CodeSample(
                    '$table->polygon(\'area\')',
                    '$table->geometry(\'area\', subtype: \'polygon\')',
                ),
            ],
        );
    }
}
