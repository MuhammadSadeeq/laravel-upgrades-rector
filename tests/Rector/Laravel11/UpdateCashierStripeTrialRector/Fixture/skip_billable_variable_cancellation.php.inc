<?php

class PaymentProcessor
{
    public function processCancel($billable)
    {
        $billable->cancel();
    }
}

?>
