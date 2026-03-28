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
-----
<?php

use Laravel\Telescope\Telescope;

class AppServiceProvider
{
    public function register()
    {
        // Laravel 11 Telescope 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=telescope-migrations
        Telescope::recordQueries();
        // Laravel 11 Telescope 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=telescope-migrations
        Telescope::recordRequests();
    }
}

?>
