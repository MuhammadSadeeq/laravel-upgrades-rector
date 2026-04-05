<?php

use Illuminate\Support\Manager;

function registerDriver(Manager $manager)
{
    $manager->extend('custom', function () {
        return new CustomDriver();
    });
}

?>
