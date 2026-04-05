<?php

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

// Laravel 13: default Redis prefixes now use hyphenated suffixes. Set REDIS_PREFIX explicitly if you need the previous generated value.
return [
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
        ],
    ],
];

?>
