<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeRector;

use Laravel\Cashier\Billable;

class BillableUser
{
    use Billable;

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

namespace App\Fixtures\Laravel11\UpdateCashierStripeRector;

use Laravel\Cashier\Billable;

class BillableUser
{
    use Billable;

    public function example()
    {
        $user = new BillableUser();
        $hasPayment = $user->hasPaymentMethod('card');
        $methods = $user->paymentMethods('card');
        $user->deletePaymentMethods('card');
    }
}

?>
