<?php

return [
    'driver' => 'mysql',
    'dbal' => [
        'types' => [
            'timestamp' => App\Database\Types\TimestampType::class,
        ],
        'mapping_types' => [
            'enum' => 'string',
        ],
    ],
];

?>
-----
<?php

return [
    'driver' => 'mysql',
    'dbal' => [
        'mapping_types' => [
            'enum' => 'string',
        ],
    ],
];

?>
