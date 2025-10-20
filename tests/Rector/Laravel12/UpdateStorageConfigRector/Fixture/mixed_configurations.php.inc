<?php

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => 'storage/app', // Should change
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'root' => 'storage/app', // Should not change - S3 driver
            'bucket' => 'my-bucket',
        ],

        'backup' => [
            'driver' => 'local',
            'root' => '/backup/storage/app', // Should change
        ],

        'temp' => [
            'driver' => 'local',
            'root' => '/tmp/files', // Should not change - different path
        ],

        'already_private' => [
            'driver' => 'local',
            'root' => 'storage/app/private', // Should not change - already correct
        ],
    ],
];

?>
-----
<?php

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            /** Laravel 12: Local filesystem disk now defaults to storage/app/private */
            'root' => 'storage/app/private', // Should change
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'root' => 'storage/app', // Should not change - S3 driver
            'bucket' => 'my-bucket',
        ],

        'backup' => [
            'driver' => 'local',
            /** Laravel 12: Local filesystem disk now defaults to storage/app/private */
            'root' => '/backup/storage/app/private', // Should change
        ],

        'temp' => [
            'driver' => 'local',
            'root' => '/tmp/files', // Should not change - different path
        ],

        'already_private' => [
            'driver' => 'local',
            'root' => 'storage/app/private', // Should not change - already correct
        ],
    ],
];

?>