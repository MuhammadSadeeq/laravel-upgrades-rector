<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),

    'middleware' => [
        'authenticate_session' => 'App\Http\Middleware\AuthenticateSession',
        'encrypt_cookies' => 'App\Http\Middleware\EncryptCookies',
        'verify_csrf_token' => 'App\Http\Middleware\VerifyCsrfToken',
    ],

    'expiration' => null,
];

?>
-----
<?php

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
