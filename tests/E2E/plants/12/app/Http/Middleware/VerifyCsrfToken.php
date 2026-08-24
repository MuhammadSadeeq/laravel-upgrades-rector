<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class VerifyCsrfToken extends ValidateCsrfToken
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var list<string>
     */
    protected $except = [
        'webhook/*',
    ];
}
