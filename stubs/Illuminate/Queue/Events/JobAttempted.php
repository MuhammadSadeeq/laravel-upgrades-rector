<?php

namespace Illuminate\Queue\Events;

class JobAttempted
{
    public string $connectionName;

    public mixed $job;

    public ?\Throwable $exception;
}
