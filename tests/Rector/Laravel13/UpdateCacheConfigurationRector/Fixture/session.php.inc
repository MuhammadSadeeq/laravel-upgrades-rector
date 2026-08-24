<?php

namespace App\Fixtures\Laravel13\UpdateCacheConfigurationRector;

return [
    'driver' => env('SESSION_DRIVER', 'database'),
];

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateCacheConfigurationRector;

return [
    // Laravel 13: default session cookie names now use Str::snake(APP_NAME). Set SESSION_COOKIE explicitly if you need the previous generated value.
    'driver' => env('SESSION_DRIVER', 'database'),
];

?>
