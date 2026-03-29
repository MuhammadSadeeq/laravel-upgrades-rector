<?php

namespace Illuminate\Queue\Events;

class QueueBusy
{
    public string $connectionName;

    public string $queue;

    public int $size;
}
