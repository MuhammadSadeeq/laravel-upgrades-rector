<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel 12: DatabaseTokenRepository's $expires parameter now expects
 * seconds, not minutes. Passing the old minute-based value (e.g. 60) results
 * in a token that expires in 1 hour instead of 60 minutes — a security issue.
 *
 * @implements Rule<New_>
 */
final class DatabaseTokenRepositoryExpiresRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Name) {
            return [];
        }

        $raw = ltrim($node->class->toString(), '\\');

        if ($raw !== 'Illuminate\Auth\Passwords\DatabaseTokenRepository'
            && $raw !== 'DatabaseTokenRepository') {
            return [];
        }

        // The $expires parameter is at index 4.
        if (count($node->args) < 5) {
            return [];
        }

        $expiresArg = $node->args[4];

        if (! $expiresArg instanceof Arg) {
            return [];
        }

        if ($expiresArg->value instanceof LNumber && $expiresArg->value->value < 600) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'DatabaseTokenRepository $expires is now in seconds. The value %d may be too short (was previously interpreted as minutes).',
                        $expiresArg->value->value
                    )
                )->identifier('laravelUpgrade.databaseTokenRepositoryExpires')
                    ->tip('Multiply the old minute value by 60 to convert to seconds.')
                    ->build(),
            ];
        }

        return [];
    }
}
