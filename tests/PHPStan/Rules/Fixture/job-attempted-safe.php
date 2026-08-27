<?php

use Illuminate\Queue\Events\JobAttempted;

function inspectAttemptedSafely(JobAttempted $event): void
{
    $exception = $event->exception === null;
    $event->successful();

    if ($event->exception) {
        $event->successful();
    }
}
