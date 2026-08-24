<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

class PaymentProcessor
{
    public function processCancel($billable)
    {
        $billable->cancel();
    }
}

?>
