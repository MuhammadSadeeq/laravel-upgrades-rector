<?php

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Existing boot code
    }

    public function register(): void
    {
        // Register code
    }
}

?>
-----
<?php

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Laravel 11 Passport 12: Password grant type is disabled by default. Add Passport::enablePasswordGrant() in this method if you need password grants. */
    public function boot(): void
    {
        // Existing boot code
    }

    public function register(): void
    {
        // Register code
    }
}

?>