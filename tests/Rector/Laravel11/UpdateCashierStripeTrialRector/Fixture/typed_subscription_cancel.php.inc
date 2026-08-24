<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

use Laravel\Cashier\Subscription;

class SubscriptionManager
{
    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->cancel();
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

use Laravel\Cashier\Subscription;

class SubscriptionManager
{
    public function cancelSubscription(Subscription $subscription)
    {
        // @laravel-upgrade cashier-trial-cancel cancel() now always ends subscription trials immediately
        $subscription->cancel();
    }
}

?>
