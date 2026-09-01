<?php

return [
    // 'default' => 'comment-driver',
    'default' => env('DB_CONNECTION', 'sqlite'),
    'notes' => "'driver' => 'string-decoy'",
    'unrelated' => [
        'default' => 'nested-default-decoy',
        'driver' => 'nested-driver-decoy',
    ],
    'connections' => [
        'sqlite' => ['driver' => 'sqlite'],
        'mysql' => ['driver' => 'mysql'],
        'nested' => [
            'options' => ['driver' => 'nested-option-decoy'],
            'default' => 'nested-default-decoy',
        ],
    ],
];
