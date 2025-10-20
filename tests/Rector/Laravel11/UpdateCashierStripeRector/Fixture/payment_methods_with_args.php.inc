<?php

class BillableUser
{
    public function example()
    {
        $user = new BillableUser();

        // These should not be changed as they already have arguments
        $hasPayment = $user->hasPaymentMethod('sepa_debit');
        $methods = $user->paymentMethods(['card', 'us_bank_account']);
        $user->deletePaymentMethods($paymentMethods);
    }
}

?>