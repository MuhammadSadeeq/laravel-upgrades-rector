<?php

use Illuminate\Auth\AuthenticationException;

function handle(AuthenticationException $exception): string
{
    $path = $exception->redirectTo();

    return $path ?? '/login';
}

?>
-----
<?php

use Illuminate\Auth\AuthenticationException;

function handle(AuthenticationException $exception): string
{
    // Laravel 11: AuthenticationException::redirectTo() now requires the current Request instance. Pass $request to redirectTo().
    $path = $exception->redirectTo();

    return $path ?? '/login';
}

?>
