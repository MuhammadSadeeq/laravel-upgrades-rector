<?php

return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => '/custom/storage/path', // Should not change - different path
        ],

        'temp' => [
            'driver' => 'local',
            'root' => 'storage/temp', // Should not change - different path
        ],

        'uploads' => [
            'driver' => 'local',
            'root' => 'public/uploads', // Should not change - different path
        ],
    ],
];

?>