<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),

    'middleware' => [
        'authenticate_session' => App\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],

    'expiration' => null,
];

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),

    'middleware' => [
        'authenticate_session' => App\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],

    'expiration' => null,
];

?>
