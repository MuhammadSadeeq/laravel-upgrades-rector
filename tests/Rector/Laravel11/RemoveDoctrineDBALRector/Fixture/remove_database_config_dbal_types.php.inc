<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

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

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
        ],
    ],
];

?>
