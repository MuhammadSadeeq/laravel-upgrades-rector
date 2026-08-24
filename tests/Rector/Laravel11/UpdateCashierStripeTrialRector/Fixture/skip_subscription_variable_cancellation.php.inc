<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

class OtherService
{
    public function handleOtherCancellations()
    {
        $order->cancel();
        $booking->cancelNow();
        $appointment->cancelAt(now()->addHour());
    }
}

?>
