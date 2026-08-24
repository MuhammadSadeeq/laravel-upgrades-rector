<?php

namespace App\Fixtures\Laravel11\UpdateTelescopeRector;

use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;

class TelescopeServiceProvider
{
    public function boot()
    {
        Telescope::filter(function (IncomingEntry $entry) {
            return $entry->isReportableException();
        });

        Telescope::tag(function (IncomingEntry $entry) {
            return ['user:1'];
        });
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateTelescopeRector;

use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;

class TelescopeServiceProvider
{
    public function boot()
    {
        // Laravel 11 Telescope 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=telescope-migrations
        Telescope::filter(function (IncomingEntry $entry) {
            return $entry->isReportableException();
        });

        // Laravel 11 Telescope 5: Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=telescope-migrations
        Telescope::tag(function (IncomingEntry $entry) {
            return ['user:1'];
        });
    }
}

?>
