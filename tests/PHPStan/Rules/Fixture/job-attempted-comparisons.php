<?php

use Illuminate\Queue\Events\JobAttempted;

function inspectAttemptedComparisons(JobAttempted $event): void
{
    $old = $event->exceptionOccurred === true;
    $new = $event->exception !== false;
}
