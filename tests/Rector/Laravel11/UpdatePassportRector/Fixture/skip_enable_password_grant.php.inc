<?php

namespace App\Fixtures\Laravel11\UpdatePassportRector;

use Laravel\Passport\Passport;

class AuthSetup
{
    public function boot()
    {
        Passport::enablePasswordGrant();
    }
}

?>
