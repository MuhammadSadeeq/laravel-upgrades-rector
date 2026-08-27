<?php

namespace App;

class LocalSubscription
{
    public function cancel(): void {}
}

function cancelLocalSubscription(LocalSubscription $subscription): void
{
    $subscription->cancel();
}
