<?php

namespace App;

use Illuminate\Auth\AuthenticationException;

function redirectWithoutRequest(AuthenticationException $exception): ?string
{
    return $exception->redirectTo();
}
