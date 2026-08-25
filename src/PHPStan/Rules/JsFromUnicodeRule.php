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
 * Laravel 13: Js::from() now escapes Unicode characters differently.
 * Flags Js::from() calls so developers verify the new escaping behaviour.
 *
 * @implements Rule<StaticCall>
 */
final class JsFromUnicodeRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'from') {
            return [];
        }

        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($node->class->toString(), '\\');

        if (! in_array($raw, ['Js', 'Illuminate\Support\Js'], true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Js::from() Unicode escaping behaviour changed in Laravel 13.'
            )->identifier('laravelUpgrade.jsFromUnicode')
                ->tip('Verify that the output HTML/JSON handles the new escaping correctly.')
                ->build(),
        ];
    }
}
