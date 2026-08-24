<?php

namespace App\Fixtures\Laravel11\UpdateAuthenticationExceptionRedirectToRector;

class RedirectTarget
{
    public function redirectTo(): string
    {
        return '/dashboard';
    }
}

$path = (new RedirectTarget())->redirectTo();

?>
