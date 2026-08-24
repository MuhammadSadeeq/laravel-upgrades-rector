<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Manager;

function registerDriver(Manager $manager)
{
    $manager->extend('custom', function () {
        return new CustomDriver();
    });
}

?>
