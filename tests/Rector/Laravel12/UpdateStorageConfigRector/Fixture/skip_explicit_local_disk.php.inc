<?php

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
        's3' => [
            'driver' => 's3',
        ],
    ],
];
