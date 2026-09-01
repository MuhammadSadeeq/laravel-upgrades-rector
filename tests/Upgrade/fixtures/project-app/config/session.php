<?php

return [
    // 'driver' => 'comment-session',
    'driver' => env('SESSION_DRIVER', 'file'),
    'serialization' => env('SESSION_SERIALIZATION', 'php'),
    'nested' => [
        'driver' => 'nested-session-decoy',
        'serialization' => 'nested-serialization-decoy',
    ],
];
