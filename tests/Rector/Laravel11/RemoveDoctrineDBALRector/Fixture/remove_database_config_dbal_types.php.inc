<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'dbal' => [
                'types' => [
                    'timestamp' => App\Database\Types\TimestampType::class,
                ],
            ],
        ],
    ],
];

?>
-----
<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
        ],
    ],
];

?>
