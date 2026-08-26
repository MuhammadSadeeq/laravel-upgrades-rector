<?php

return [
    'cookie' => env(
        'SESSION_COOKIE',
        str_replace('_', '-', strtolower(env('APP_NAME', 'laravel'))).'-session'
    ),
];
