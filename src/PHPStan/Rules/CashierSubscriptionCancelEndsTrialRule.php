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

/** Cashier 15: cancelling a subscription ends a trial immediately. */
/** @implements Rule<MethodCall> */
final class CashierSubscriptionCancelEndsTrialRule implements Rule
{
    /** @var list<string> */
    private const CANCEL_METHODS = ['cancel', 'cancelnow', 'cancelat'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall || ! $node->name instanceof Identifier
            || ! in_array($node->name->toLowerString(), self::CANCEL_METHODS, true)) {
            return [];
        }

        if (! (new ObjectType('Laravel\\Cashier\\Subscription'))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf('%s() now ends Cashier subscription trials immediately.', $node->name->toString())
            )->identifier('laravelUpgrade.cashierSubscriptionCancelEndsTrial')
                ->tip('Use an explicit trial-ending flow when preserving the previous cancellation timing.')
                ->build(),
        ];
    }
}
