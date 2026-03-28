<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),

    'middleware' => [
        'authenticate_session' => 'App\Http\Middleware\CustomAuthSession',
        'encrypt_cookies' => 'App\Http\Middleware\CustomEncryptCookies',
        'validate_csrf_token' => 'App\Http\Middleware\CustomCsrfToken',
    ],

    'expiration' => null,
];

?>
