<?php

use Illuminate\Queue\Events\JobAttempted;

function inspectAttemptedScalarAssignments(JobAttempted $event, int $count, string $message): void
{
    $count = $event->exception;
    $message = $event->exceptionOccurred;
}
