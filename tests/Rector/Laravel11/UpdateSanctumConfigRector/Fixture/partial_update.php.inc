<?php

return [
    'middleware' => [
        'authenticate_session' => 'Laravel\Sanctum\Http\Middleware\AuthenticateSession::class',
        'encrypt_cookies' => 'App\Http\Middleware\EncryptCookies',
        'validate_csrf_token' => 'App\Http\Middleware\VerifyCsrfToken',
    ],
];

?>
-----
<?php

return [
    'middleware' => [
        'authenticate_session' => 'Laravel\Sanctum\Http\Middleware\AuthenticateSession::class',
        'encrypt_cookies' => 'Illuminate\Cookie\Middleware\EncryptCookies::class',
        'validate_csrf_token' => 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class',
    ],
];

?>