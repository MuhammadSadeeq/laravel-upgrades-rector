<?php

namespace App\Services;

class SomeService
{
    public function handle(object $event): void
    {
        $value = $event->exceptionOccurred;
    }
}

?>
