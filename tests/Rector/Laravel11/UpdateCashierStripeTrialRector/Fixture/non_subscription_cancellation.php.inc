<?php

class OtherService
{
    public function handleOtherCancellations()
    {
        // These should not be changed as they don't appear to be subscription-related
        $order->cancel();
        $booking->cancelNow();
        $appointment->cancelAt(now()->addHour());

        $regularVariable = new SomeClass();
        $regularVariable->cancel();
    }
}

?>