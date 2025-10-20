<?php

class SubscriptionService
{
    public function manageSub()
    {
        // Variable containing 'sub' should be detected
        $sub = $user->subscription('default');
        $sub->cancel();

        $activeSub = $customer->getActiveSubscription();
        $activeSub->cancelNow();

        $userSubscription = $this->getUserSubscription();
        $userSubscription->cancelAt(now()->endOfMonth());
    }
}

?>
-----
<?php

class SubscriptionService
{
    public function manageSub()
    {
        // Variable containing 'sub' should be detected
        $sub = $user->subscription('default');
        $sub->cancel();

        $activeSub = $customer->getActiveSubscription();
        $activeSub->cancelNow();

        $userSubscription = $this->getUserSubscription();
        $userSubscription->cancelAt(now()->endOfMonth());
    }
}

?>