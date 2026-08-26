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
 * Carbon 3: setTestNow() stores a copy of the date instead of a reference.
 * Flags setTestNow() calls so developers verify test behaviour.
 *
 * @implements Rule<StaticCall>
 */
final class SetTestNowCopySemanticsRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || $node->name->toLowerString() !== 'settestnow') {
            return [];
        }

        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($node->class->toString(), '\\');

        if (! str_contains($raw, 'Carbon')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Carbon 3 setTestNow() stores a copy of the date, not a reference.'
            )->identifier('laravelUpgrade.setTestNowCopySemantics')
                ->tip('Verify that tests relying on shared state still pass with the copy semantics.')
                ->build(),
        ];
    }
}
