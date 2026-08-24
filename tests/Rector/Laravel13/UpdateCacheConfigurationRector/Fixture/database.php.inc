<?php

namespace App\Fixtures\Laravel13\UpdateCacheConfigurationRector;

return [
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
        ],
    ],
];

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateCacheConfigurationRector;

return [
    // Laravel 13: default Redis prefixes now use hyphenated suffixes. Set REDIS_PREFIX explicitly if you need the previous generated value.
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
        ],
    ],
];

?>
