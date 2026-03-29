<?php

namespace App\Listeners;

use Illuminate\Queue\Events\QueueBusy;

class QueueBusyListener
{
    public function handle(QueueBusy $event): void
    {
        $value = $event->connectionName;
    }
}

?>
