<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'),
];

?>
-----
<?php

// Laravel 13: default session cookie names now use Str::snake(APP_NAME). Set SESSION_COOKIE explicitly if you need the previous generated value.
return [
    'driver' => env('SESSION_DRIVER', 'database'),
];

?>
