<?php

namespace App;

use Laravel\Cashier\Subscription;

function cancelTrialAtPositive(Subscription $subscription): void
{
    $subscription->cancelAt(now());
}
