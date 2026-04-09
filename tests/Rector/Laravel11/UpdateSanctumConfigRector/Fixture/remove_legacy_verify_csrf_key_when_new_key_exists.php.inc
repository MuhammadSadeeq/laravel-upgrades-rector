<?php

return [
    'stateful' => [],

    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'verify_csrf_token' => 'App\Http\Middleware\VerifyCsrfToken',
        'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    'expiration' => null,
];

?>
-----
<?php

return [
    'stateful' => [],

    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    'expiration' => null,
];

?>
