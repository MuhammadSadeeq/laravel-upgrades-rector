<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

class CustomRegistry
{
    public function extend(string $driver, Closure $callback): void
    {
    }
}

function registerDriver(CustomRegistry $registry)
{
    $registry->extend('custom', function () {
        return $this->app->make(CustomDriver::class);
    });
}

?>
