<?php

namespace App;

use Laravel\Passport\Passport as PassportFacade;

function registerQualifiedPassportRoutes(): void
{
    PassportFacade::routes();
}
