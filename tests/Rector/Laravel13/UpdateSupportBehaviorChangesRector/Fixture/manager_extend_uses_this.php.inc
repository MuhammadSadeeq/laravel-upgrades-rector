<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Manager;

function registerDriver(Manager $manager)
{
    $manager->extend('custom', function () {
        return $this->app->make(CustomDriver::class);
    });
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Manager;

function registerDriver(Manager $manager)
{
    // Laravel 13: manager extend callbacks are now bound to the manager instance. Capture dependencies with use (...) instead of relying on $this.
    $manager->extend('custom', function () {
        return $this->app->make(CustomDriver::class);
    });
}

?>
