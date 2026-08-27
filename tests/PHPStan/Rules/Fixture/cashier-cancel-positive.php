<?php

namespace App;

use Laravel\Cashier\Subscription;

function cancelTrialPositive(Subscription $subscription): void
{
    $subscription->cancel();
}
