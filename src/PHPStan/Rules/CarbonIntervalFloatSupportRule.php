<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Carbon 3: CarbonInterval::fromString() and related methods now support
 * float values (e.g. "1.5 hours"). Flags CarbonInterval creation calls so
 * developers verify float handling.
 *
 * @implements Rule<StaticCall>
 */
final class CarbonIntervalFloatSupportRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toLowerString();

        if ($methodName !== 'create' && $methodName !== 'fromstring' && $methodName !== 'instance') {
            return [];
        }

        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($node->class->toString(), '\\');

        if (! str_contains($raw, 'CarbonInterval') && ! str_contains($raw, 'Interval')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'CarbonInterval now supports float values in Carbon 3.'
            )->identifier('laravelUpgrade.carbonIntervalFloat')
                ->tip('Verify that fractional intervals behave as expected.')
                ->build(),
        ];
    }
}
