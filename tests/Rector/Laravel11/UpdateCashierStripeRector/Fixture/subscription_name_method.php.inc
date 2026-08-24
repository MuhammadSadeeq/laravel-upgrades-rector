<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeRector;

use Laravel\Cashier\Cashier;

class Config
{
    public function setup()
    {
        Cashier::useCustomerModel(User::class);
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeRector;

use Laravel\Cashier\Cashier;

class Config
{
    public function setup()
    {
        // @laravel-upgrade cashier-card-default Review Cashier static configuration for v15 compatibility
        Cashier::useCustomerModel(User::class);
    }
}

?>
