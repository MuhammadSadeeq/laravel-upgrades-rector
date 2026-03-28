<?php

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
