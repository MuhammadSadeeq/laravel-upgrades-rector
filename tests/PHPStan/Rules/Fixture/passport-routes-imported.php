<?php

namespace App;

use Laravel\Passport\Passport;

function registerPassportRoutes(): void
{
    Passport::routes();
}
