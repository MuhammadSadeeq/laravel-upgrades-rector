<?php

return [
    'default' => env('CACHE_STORE', 'database'),
    'serializable_classes' => false,
    'stores' => [
        'database' => [
            'connection' => env('DB_CACHE_CONNECTION'),
        ],
    ],
];
