<?php

class BillableUser
{
    public function example()
    {
        $user = new BillableUser();
        $hasPayment = $user->hasPaymentMethod();
        $methods = $user->paymentMethods();
        $user->deletePaymentMethods();
    }
}

?>
-----
<?php

class BillableUser
{
    public function example()
    {
        $user = new BillableUser();
        $hasPayment = $user->hasPaymentMethod('card');
        $methods = $user->paymentMethods('card');
        $user->deletePaymentMethods('card');
    }
}

?>
