<?php

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

use Laravel\Cashier\Subscription;

class SubscriptionManager
{
    public function cancelSubscription(Subscription $subscription)
    {
        // Cashier Stripe 15: cancel() now always ends subscription trials immediately
        $subscription->cancel();
    }
}

?>
