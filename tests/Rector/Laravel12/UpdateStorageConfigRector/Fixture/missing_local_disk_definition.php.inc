<?php

namespace App\Fixtures\Laravel12\UpdateStorageConfigRector;

return [
    'disks' => [
        's3' => [
            'driver' => 's3',
            'bucket' => env('AWS_BUCKET'),
        ],
    ],
];

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateStorageConfigRector;

return [
    /** Laravel 12: If no "local" disk is explicitly defined, Laravel now defaults it to storage/app/private. Define disks.local.root explicitly to preserve storage/app. */
    'disks' => [
        's3' => [
            'driver' => 's3',
            'bucket' => env('AWS_BUCKET'),
        ],
    ],
];

?>
