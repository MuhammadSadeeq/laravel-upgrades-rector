<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Sets\Laravel11;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateSpatialTypesRector extends AbstractRector
{
    private array $removedSpatialMethods = [
        "point",
        "lineString",
        "polygon",
        "geometryCollection",
        "multiPoint",
        "multiLineString",
        "multiPolygon",
        "multiPolygonZ",
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

        $methodName = $this->getName($node->name);

        // Check if this is a removed spatial method
        if (in_array($methodName, $this->removedSpatialMethods, true)) {
            // Replace with geometry method
            $node->name = new Identifier("geometry");

            // Add comment about the change
            $node->setAttribute("comments", [
                new \PhpParser\Comment\Doc(
                    "/** Laravel 11: {$methodName}() method removed. " .
                        "Use geometry() or geography() instead. " .
                        "For specific types, use subtype parameter: geometry('column', subtype: '{$methodName}') */",
                ),
            ]);

            return $node;
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Update spatial column types to use geometry/geography methods for Laravel 11",
            [
                new CodeSample(
                    '$table->point(\'coordinates\')',
                    '/** Laravel 11: point() method removed. Use geometry() or geography() instead. For specific types, use subtype parameter: geometry(\'column\', subtype: \'point\') */
$table->geometry(\'coordinates\')',
                ),
                new CodeSample(
                    '$table->polygon(\'area\')',
                    '/** Laravel 11: polygon() method removed. Use geometry() or geography() instead. For specific types, use subtype parameter: geometry(\'column\', subtype: \'polygon\') */
$table->geometry(\'area\')',
                ),
            ],
        );
    }
}
