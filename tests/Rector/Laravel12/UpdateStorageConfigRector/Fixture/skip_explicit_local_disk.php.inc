<?php

namespace App\Fixtures\Laravel12\UpdateStorageConfigRector;

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
