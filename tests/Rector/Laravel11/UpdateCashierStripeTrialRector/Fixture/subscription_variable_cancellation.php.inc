<?php

class SubscriptionHandler
{
    public function cancelSubscription()
    {
        $subscription = auth()->user()->subscription('default');
        $subscription->cancel();

        $activeSubscription = $user->subscription('premium');
        $activeSubscription->cancelNow();

        $trialSub = $customer->subscription('trial');
        $trialSub->cancelAt(now()->addDays(7));
    }
}

?>
-----
<?php

class SubscriptionHandler
{
    public function cancelSubscription()
    {
        $subscription = auth()->user()->subscription('default');
        $subscription->cancel();

        $activeSubscription = $user->subscription('premium');
        $activeSubscription->cancelNow();

        $trialSub = $customer->subscription('trial');
        $trialSub->cancelAt(now()->addDays(7));
    }
}

?>