<?php

class CustomScheduler
{
    public function withScheduling(callable $callback): void
    {
    }
}

function configure(CustomScheduler $scheduler)
{
    $scheduler->withScheduling(function () {
    });
}

?>
