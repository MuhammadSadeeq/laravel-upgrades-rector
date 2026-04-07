<?php

class PaymentGateway
{
    public function hasPaymentMethod()
    {
        return false;
    }
}

class Example
{
    public function run()
    {
        $gateway = new PaymentGateway();

        return $gateway->hasPaymentMethod();
    }
}

?>
