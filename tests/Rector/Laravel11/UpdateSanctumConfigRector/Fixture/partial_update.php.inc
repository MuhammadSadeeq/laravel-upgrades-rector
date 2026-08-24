<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

return [
    'stateful' => [],

    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => 'App\Http\Middleware\EncryptCookies',
        'verify_csrf_token' => 'App\Http\Middleware\VerifyCsrfToken',
    ],

    'expiration' => null,
];

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

return [
    'stateful' => [],

    'middleware' => [
        'authenticate_session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    'expiration' => null,
];

?>
