<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\NodeAnalyzer;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;

/**
 * Argument inspection helpers shared by transform rules
 * rule standard #5): named arguments are skipped or mapped explicitly,
 * first-class callables and spreads never get rewritten silently.
 */
final class ArgumentHelper
{
    /**
     * @param  MethodCall|StaticCall  $call
     */
    public function hasNamedArguments($call): bool
    {
        foreach ($call->getArgs() as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($arg->name !== null || $arg->unpack) {
                return true;
            }
        }

        return false;
    }

    public function hasUnpack(MethodCall|StaticCall $call): bool
    {
        foreach ($call->getArgs() as $arg) {
            if ($arg instanceof Arg && $arg->unpack) {
                return true;
            }
        }

        return false;
    }

    /**
     * Positional argument by index, or named argument by name.
     */
    public function argByNameOrPosition(MethodCall|StaticCall $call, ?int $index, ?string $name = null): ?Arg
    {
        foreach ($call->getArgs() as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($name !== null && $arg->name !== null && $arg->name->toLowerString() === strtolower($name)) {
                return $arg;
            }
        }

        if ($index !== null) {
            $positional = array_values(array_filter(
                $call->getArgs(),
                static fn ($arg): bool => $arg instanceof Arg && $arg->name === null
            ));

            return $positional[$index] ?? null;
        }

        return null;
    }
}
