<?php

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => 'storage/app/private',
        ],

        'custom' => [
            'driver' => 'local',
            'root' => '/custom/path/storage/app/private',
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
            'root' => 'storage/app/private',
        ],

        'custom' => [
            'driver' => 'local',
            'root' => '/custom/path/storage/app/private',
        ],
    ],
];

?>