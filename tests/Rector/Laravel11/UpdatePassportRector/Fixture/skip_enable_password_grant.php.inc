<?php

use Laravel\Passport\Passport;

class AuthSetup
{
    public function boot()
    {
        Passport::enablePasswordGrant();
    }
}

?>
