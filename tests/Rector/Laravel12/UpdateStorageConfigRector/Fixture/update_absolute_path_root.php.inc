<?php

return [
    'disks' => [
        'custom_local' => [
            'driver' => 'local',
            'root' => '/var/www/html/storage/app',
            'visibility' => 'private',
        ],
    ],
];

?>
-----
<?php

return [
    'disks' => [
        'custom_local' => [
            'driver' => 'local',
            /** Laravel 12: Local filesystem disk now defaults to storage/app/private */
            'root' => '/var/www/html/storage/app/private',
            'visibility' => 'private',
        ],
    ],
];

?>