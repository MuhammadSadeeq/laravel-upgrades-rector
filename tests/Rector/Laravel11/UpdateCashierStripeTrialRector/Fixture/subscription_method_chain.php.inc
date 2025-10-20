<?php

class BillingManager
{
    public function handleCancellations()
    {
        // Method chain cancellations
        $user->subscription('default')->cancel();
        $customer->newSubscription('premium', 'price_123')->cancelNow();
        $billable->subscriptions()->first()->cancelAt(now()->addMonth());
    }
}

?>
-----
<?php

class BillingManager
{
    public function handleCancellations()
    {
        // Method chain cancellations
        $user->subscription('default')->cancel();
        $customer->newSubscription('premium', 'price_123')->cancelNow();
        $billable->subscriptions()->first()->cancelAt(now()->addMonth());
    }
}

?>