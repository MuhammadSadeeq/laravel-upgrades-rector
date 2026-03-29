<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class Kernel
{
    protected $middleware = [
        PreventRequestForgery::class,
    ];
}

?>
