<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

class OrderService
{
    public function cancel()
    {
        $order = new Order();
        $order->cancel();
        $order->cancelNow();
    }
}

?>
