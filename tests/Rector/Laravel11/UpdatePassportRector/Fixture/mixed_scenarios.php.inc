<?php

namespace App\Fixtures\Laravel11\UpdatePassportRector;

use Laravel\Passport\Passport;

class PassportSetup
{
    public function boot(): void
    {
        Passport::routes();
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdatePassportRector;

use Laravel\Passport\Passport;

class PassportSetup
{
    public function boot(): void
    {
        // Laravel 11 Passport 12: Password grant disabled by default. Migrations no longer auto-loaded. Run: php artisan vendor:publish --tag=passport-migrations
        Passport::routes();
    }
}

?>
