<?php

class RedirectTarget
{
    public function redirectTo(): string
    {
        return '/dashboard';
    }
}

$path = (new RedirectTarget())->redirectTo();

?>
