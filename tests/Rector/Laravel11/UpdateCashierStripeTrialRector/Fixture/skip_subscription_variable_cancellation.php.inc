<?php

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
