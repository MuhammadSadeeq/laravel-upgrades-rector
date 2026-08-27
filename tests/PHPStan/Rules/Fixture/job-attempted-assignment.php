<?php

use Illuminate\Queue\Events\JobAttempted;

function inspectAttemptedAssignment(JobAttempted $event, bool $failed): void
{
    $failed = $event->exception;
}
