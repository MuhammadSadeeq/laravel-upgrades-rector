<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12 changed HasUuids to generate UUIDv7 by default. Models that
 * relied on ordered UUIDv4 must explicitly use HasVersion4Uuids.
 *
 * @implements Rule<TraitUse>
 */
final class HasUuidsNowV7Rule implements Rule
{
    public function getNodeType(): string
    {
        return TraitUse::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $matched = false;

        foreach ($node->traits as $trait) {
            if (! $trait instanceof Name) {
                continue;
            }

            $traitName = ltrim($trait->toString(), '\\');

            if (strcasecmp($traitName, 'Illuminate\Database\Eloquent\Concerns\HasUuids') === 0
                || strcasecmp($traitName, 'HasUuids') === 0
            ) {
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
