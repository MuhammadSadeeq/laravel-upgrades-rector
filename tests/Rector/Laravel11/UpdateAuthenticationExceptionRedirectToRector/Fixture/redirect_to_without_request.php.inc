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
        // Laravel 11: AuthenticationException::redirectTo() now requires the current Request instance. Pass $request to redirectTo().
        $path = $e->redirectTo();
    }
}

?>
