<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobAttempted;

class JobAttemptedListener
{
    public function handle(JobAttempted $event): void
    {
        $value = $event->exception;
    }
}

?>
