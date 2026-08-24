<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12 changed HasUuids to generate UUIDv7 by default. Models that
 * relied on ordered UUIDv4 must explicitly use HasVersion4Uuids.
 *
 * Flags `use HasUuids` trait imports on Model classes so the developer can
 * confirm v7 is acceptable or switch to HasVersion4Uuids.
 */
final class HasUuidsNowV7Rule implements Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\TraitUse::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $matched = false;

        foreach ($node->traits as $trait) {
            $traitName = $trait->toString();

            if (strcasecmp($traitName, 'Illuminate\Database\Eloquent\Concerns\HasUuids') === 0
                || strcasecmp($traitName, 'HasUuids') === 0) {
                $matched = true;

                break;
            }
        }

        if (! $matched) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'HasUuids now generates UUIDv7 by default in Laravel 12.'
            )->identifier('laravelUpgrade.hasUuidsNowV7')
                ->tip('Switch to Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids if you need the previous ordered UUIDv4 behaviour.')
                ->build(),
        ];
    }
}
