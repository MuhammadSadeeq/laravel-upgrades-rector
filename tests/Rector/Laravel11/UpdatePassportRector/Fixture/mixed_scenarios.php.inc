<?php

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Passport::routes();

        // Other boot code
    }

    public function register(): void
    {
        // Register code
    }
}

class OtherClass
{
    public function setup()
    {
        Passport::tokensExpireIn(now()->addDays(15));

        // Other setup
    }
}

?>
-----
<?php

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /** Laravel 11 Passport 12: Password grant type is disabled by default. Add Passport::enablePasswordGrant() in this method if you need password grants. */
    public function boot(): void
    {
        Passport::routes();

        // Other boot code
    }

    public function register(): void
    {
        // Register code
    }
}

class OtherClass
{
    public function setup()
    {
        Passport::tokensExpireIn(now()->addDays(15));

        // Other setup
    }
}

?>