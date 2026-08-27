<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Laravel 13 defers ApplicationBuilder::withSchedule() registration until
 * Schedule is resolved. The real L13 method is withSchedule (not the old
 * withScheduling spelling).
 *
 * @implements Rule<MethodCall>
 */
final class WithScheduleTimingRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall || ! $node->name instanceof Identifier
            || $node->name->toLowerString() !== 'withschedule'
            || ! (new ObjectType('Illuminate\\Foundation\\Configuration\\ApplicationBuilder'))
                ->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'ApplicationBuilder::withSchedule() registration is deferred in Laravel 13.'
            )->identifier('laravelUpgrade.withScheduleTiming')
                ->tip('Review bootstrap logic that relied on the schedule callback running immediately.')
                ->build(),
        ];
    }
}
