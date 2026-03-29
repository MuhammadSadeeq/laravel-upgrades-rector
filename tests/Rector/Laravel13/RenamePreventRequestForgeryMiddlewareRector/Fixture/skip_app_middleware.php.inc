<?php

use App\Http\Middleware\VerifyCsrfToken;

class Kernel
{
    protected $middleware = [
        VerifyCsrfToken::class,
    ];
}

?>
