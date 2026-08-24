<?php

namespace App\Fixtures\Laravel11\UpdateAuthenticationExceptionRedirectToRector;

use Illuminate\Auth\AuthenticationException;

function handle(AuthenticationException $exception): string
{
    $path = $exception->redirectTo();

    return $path ?? '/login';
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateAuthenticationExceptionRedirectToRector;

use Illuminate\Auth\AuthenticationException;

function handle(AuthenticationException $exception): string
{
    // @laravel-upgrade auth-redirect-to AuthenticationException::redirectTo() now requires the current Request instance. Pass $request to redirectTo().
    $path = $exception->redirectTo();

    return $path ?? '/login';
}

?>
