<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 11: geography() is the preferred replacement for spatial column
 * helpers. Flags geometry()/geography() calls so developers review whether
 * geography() with an SRID fits their use case better.
 */
/**
 * @implements Rule<MethodCall>
 */
final class SpatialGeographyReviewRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toLowerString();

        if ($methodName !== 'geometry' && $methodName !== 'geography') {
            return [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType('Illuminate\Database\Schema\Blueprint'))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Review whether geography() with an SRID is more appropriate than geometry() for this column.'
            )->identifier('laravelUpgrade.spatialGeographyReview')
                ->tip('geography() uses WGS84 (SRID 4326) by default and handles spherical calculations.')
                ->build(),
        ];
    }
}
