<?php

namespace App\Fixtures\Laravel11\UpdateAuthenticationExceptionRedirectToRector;

use Illuminate\Http\Request;

function handle(Request $request): string
{
    $exception = getUnknownException();
    $path = $exception->redirectTo();

    return $path ?? '/login';
}
