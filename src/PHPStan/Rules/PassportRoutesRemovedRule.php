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
 * Laravel 11 / Passport 12 removed Passport::routes() — routes are now
 * auto-registered by the service provider.
 */
/**
 * @implements Rule<StaticCall>
 */
final class PassportRoutesRemovedRule implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof StaticCall || ! $node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toLowerString() !== 'routes') {
            return [];
        }

        $class = $node->class;

        if (! $class instanceof Name) {
            return [];
        }

        $raw = ltrim($class->toString(), '\\');

        if (! in_array($raw, ['Passport', 'Laravel\Passport\Passport'], true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Passport::routes() was removed in Passport 12. Routes are now auto-registered.'
            )->identifier('laravelUpgrade.passportRoutesRemoved')
                ->tip('Remove the call; run vendor:publish --tag=passport-migrations.')
                ->build(),
        ];
    }
}
