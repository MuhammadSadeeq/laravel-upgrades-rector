<?php

namespace App;

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
            'after_commit' => true,
        ],
    ],
];
