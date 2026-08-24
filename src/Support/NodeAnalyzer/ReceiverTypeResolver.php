<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Three-valued receiver typing (plan P2-01): transform rules act only on Yes;
 * Unknown becomes an advisory finding; Never/No is a confident skip.
 */
final class ReceiverTypeResolver
{
    public const YES = 'yes';

    public const NO = 'no';

    public const UNKNOWN = 'unknown';

    public function isA(Expr $expr, string $fqcn): string
    {
        $type = $this->resolveType($expr);

        if ($type === null) {
            return self::UNKNOWN;
        }

        if ($type instanceof MixedType) {
            return self::UNKNOWN;
        }

        $objectType = new ObjectType($fqcn);

        if ($objectType->isSuperTypeOf($type)->yes()) {
            return self::YES;
        }

        if ($objectType->isSuperTypeOf($type)->no()) {
            return self::NO;
        }

        return self::UNKNOWN;
    }

    private function resolveType(Expr $expr): ?Type
    {
        $scope = $expr->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        try {
            return $scope->getType($expr);
        } catch (\Throwable) {
            return null;
        }
    }
}
