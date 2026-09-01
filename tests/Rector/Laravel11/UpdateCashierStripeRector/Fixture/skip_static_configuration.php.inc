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
