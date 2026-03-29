<?php

namespace App;

use Illuminate\Foundation\Configuration\Middleware;

function configureMiddleware(Middleware $middleware): void
{
    $middleware->validateCsrfTokens(except: ['/api/*']);
}

?>
-----
<?php

namespace App;

use Illuminate\Foundation\Configuration\Middleware;

function configureMiddleware(Middleware $middleware): void
{
    $middleware->preventRequestForgery(except: ['/api/*']);
}

?>
