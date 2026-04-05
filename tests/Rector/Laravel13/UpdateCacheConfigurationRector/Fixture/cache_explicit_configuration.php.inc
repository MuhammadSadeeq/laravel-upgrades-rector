<?php

return [
    'serializable_classes' => [
        App\Data\CachedDashboardStats::class,
    ],
    'prefix' => env('CACHE_PREFIX', 'app-cache'),
];

?>
