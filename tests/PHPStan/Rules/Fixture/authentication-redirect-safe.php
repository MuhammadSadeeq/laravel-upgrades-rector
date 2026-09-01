<?php

namespace App;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function redirectWithRequest(AuthenticationException $exception, Request $request): ?string
{
    return $exception->redirectTo($request);
}
