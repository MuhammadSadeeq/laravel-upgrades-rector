<?php

return [
    // Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.
    // Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.
    'serializable_classes' => [
        App\Data\CachedDashboardStats::class,
    ],
    'prefix' => env('CACHE_PREFIX', 'app-cache'),
];

?>
-----
<?php

return [
    // Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.
    // Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.
    'serializable_classes' => [
        App\Data\CachedDashboardStats::class,
    ],
    'prefix' => env('CACHE_PREFIX', 'app-cache'),
];

?>
