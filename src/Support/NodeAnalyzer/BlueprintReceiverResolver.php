<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Decides whether an expression is a schema Blueprint receiver.
 *
 * Only confirmed types match. A bare `$table`/`$blueprint` variable name is
 * never enough on its own — that guess corrupted unrelated classes (e.g.
 * PdfTable::float()) in earlier releases. Unresolved receivers stay
 * unresolved; they become advisory findings instead of transforms.
 */
final class BlueprintReceiverResolver
{
    public function isBlueprint(Expr $expr): bool
    {
        $type = $this->resolveType($expr);

        if ($type === null) {
            return false;
        }

        return (new ObjectType('Illuminate\Database\Schema\Blueprint'))->isSuperTypeOf($type)->yes();
    }

    /**
     * True when the receiver has a concrete non-Blueprint type, so callers can
     * distinguish "confident skip" from "unknown".
     */
    public function isDefinitelyNotBlueprint(Expr $expr): bool
    {
        $type = $this->resolveType($expr);

        if ($type === null) {
            return false;
        }

        $blueprint = new ObjectType('Illuminate\Database\Schema\Blueprint');

        return ! $blueprint->isSuperTypeOf($type)->yes() && ! $blueprint->isSuperTypeOf($type)->maybe();
    }

    private function resolveType(Expr $expr): ?Type
    {
        $scope = $expr->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        return $scope->getType($expr);
    }
}
