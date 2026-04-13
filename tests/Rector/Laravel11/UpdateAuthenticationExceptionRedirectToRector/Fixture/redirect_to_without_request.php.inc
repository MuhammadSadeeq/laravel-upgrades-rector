<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function handleAuthenticationException(Request $request): void
{
    try {
        throw new AuthenticationException();
    } catch (AuthenticationException $e) {
        $path = $e->redirectTo();
    }
}

?>
-----
<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function handleAuthenticationException(Request $request): void
{
    try {
        throw new AuthenticationException();
    } catch (AuthenticationException $e) {
        $path = $e->redirectTo($request);
    }
}

?>
