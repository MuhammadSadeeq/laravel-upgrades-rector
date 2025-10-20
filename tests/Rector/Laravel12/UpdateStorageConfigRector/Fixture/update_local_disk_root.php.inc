<?php

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => 'storage/app',
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
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
            'root' => 'storage/app/private',
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],
    ],
];

?>