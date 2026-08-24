<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeTrialRector;

class RegularService
{
    public function handle()
    {
        $service->cancel();
        $task->cancelNow();
    }
}

?>
