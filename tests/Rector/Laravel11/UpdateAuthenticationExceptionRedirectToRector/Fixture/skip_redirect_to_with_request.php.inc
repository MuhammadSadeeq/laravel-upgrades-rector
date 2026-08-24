<?php

namespace App\Fixtures\Laravel11\UpdateAuthenticationExceptionRedirectToRector;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function handleAuthenticationException(Request $request, AuthenticationException $e): void
{
    $path = $e->redirectTo($request);
}

?>
