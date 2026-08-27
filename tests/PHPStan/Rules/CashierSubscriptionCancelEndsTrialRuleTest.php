<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\CashierSubscriptionCancelEndsTrialRule;

/** @extends Laravel11RuleTestCase<CashierSubscriptionCancelEndsTrialRule> */
final class CashierSubscriptionCancelEndsTrialRuleTest extends Laravel11RuleTestCase
{
    protected function getRule(): CashierSubscriptionCancelEndsTrialRule
    {
        return new CashierSubscriptionCancelEndsTrialRule;
    }

    public function test_cancel_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/cashier-cancel-positive.php'], [[
            'cancel() now ends Cashier subscription trials immediately.',
            9,
            'Use an explicit trial-ending flow when preserving the previous cancellation timing.',
        ]]);
    }

    public function test_cancel_at_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/cashier-cancel-at.php'], [[
            'cancelAt() now ends Cashier subscription trials immediately.',
            9,
            'Use an explicit trial-ending flow when preserving the previous cancellation timing.',
        ]]);
    }

    public function test_unrelated_receiver_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/cashier-cancel-skip.php'], []);
    }
}
