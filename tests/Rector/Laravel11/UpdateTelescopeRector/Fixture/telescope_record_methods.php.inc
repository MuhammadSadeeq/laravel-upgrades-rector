<?php

use Laravel\Telescope\Telescope;

class AppServiceProvider
{
    public function register()
    {
        Telescope::recordQueries();
        Telescope::recordRequests();
    }
}

?>