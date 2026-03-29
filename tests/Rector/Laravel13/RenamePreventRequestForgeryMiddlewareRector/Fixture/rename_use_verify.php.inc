<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

class Kernel
{
    protected $middleware = [
        VerifyCsrfToken::class,
    ];
}

?>
-----
<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class Kernel
{
    protected $middleware = [
        Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    ];
}

?>
