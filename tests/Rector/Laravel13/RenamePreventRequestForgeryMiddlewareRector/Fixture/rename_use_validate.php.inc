<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class Kernel
{
    protected $middleware = [
        ValidateCsrfToken::class,
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
