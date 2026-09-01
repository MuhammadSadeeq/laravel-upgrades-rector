<?php

namespace App\Fixtures\Laravel11\UpdateAuthenticationExceptionRedirectToRector;

use Illuminate\Auth\AuthenticationException;

function handle(AuthenticationException $exception): string
{
    $path = $exception->redirectTo();

    return $path ?? '/login';
}
