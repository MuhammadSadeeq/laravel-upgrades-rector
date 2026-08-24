<?php

namespace App\Fixtures\Laravel13\UpdateCacheConfigurationRector;

return [
    'default' => env('CACHE_STORE', 'database'),
];

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateCacheConfigurationRector;

return [
    // Laravel 13: cache.serializable_classes now defaults to false. Add an explicit allow-list if your application caches PHP objects.
    // Laravel 13: default cache prefixes now use hyphenated suffixes. Set CACHE_PREFIX explicitly if you need the previous generated value.
    'default' => env('CACHE_STORE', 'database'),
];

?>
