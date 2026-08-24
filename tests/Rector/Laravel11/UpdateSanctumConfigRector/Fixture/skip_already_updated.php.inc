<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),

    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    'expiration' => null,
];

?>
