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
 * Laravel 13: Str factory instances are reset between tests by the framework.
 * Code calling Str::create() or mutating Str state inside test methods should
 * be aware that factories are now reset per-test.
 *
 * @implements Rule<StaticCall>
 */
final class StrFactoryResetRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'create') {
            return [];
        }

        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($node->class->toString(), '\\');

        // Only flag Str::create(), not other create() calls.
        if ($raw !== 'Illuminate\Support\Str' && $raw !== 'Str') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Str::create() may be affected by factory resets in Laravel 13.'
            )->identifier('laravelUpgrade.strFactoryReset')
                ->tip('Verify that custom string macros survive the per-test factory reset.')
                ->build(),
        ];
    }
}
