<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

class SubscriptionService
{
    public function manageSub()
    {
        $sub = new \stdClass();
        $sub->cancel();
    }
}

?>
