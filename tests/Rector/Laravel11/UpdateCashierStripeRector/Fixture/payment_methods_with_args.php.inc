<?php

class BillableUser
{
    public function example()
    {
        $user = new BillableUser();

        $hasPayment = $user->hasPaymentMethod('sepa_debit');
        $methods = $user->paymentMethods(['card', 'us_bank_account']);
        $user->deletePaymentMethods($paymentMethods);
    }
}

?>
