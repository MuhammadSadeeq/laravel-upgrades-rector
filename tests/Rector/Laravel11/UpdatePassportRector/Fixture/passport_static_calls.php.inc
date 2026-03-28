<?php

use Laravel\Passport\Passport;

class PassportConfiguration
{
    public function configure()
    {
        Passport::routes();
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}

?>
-----
<?php

use Laravel\Passport\Passport;

class PassportConfiguration
{
    public function configure()
    {
        // Laravel 11 Passport 12: Password grant disabled by default. Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations
        Passport::routes();
        // Laravel 11 Passport 12: Password grant disabled by default. Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations
        Passport::tokensExpireIn(now()->addDays(15));
        // Laravel 11 Passport 12: Password grant disabled by default. Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}

?>
