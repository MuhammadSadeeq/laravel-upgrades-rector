<?php

return [
    'driver' => 'redis',
    'prefix' => 'my-app',
];

?>
-----
<?php

return [
    'driver' => 'redis',
    // Laravel 11: Redis, Memcached, and DynamoDB cache prefixes no longer receive an automatic ":" suffix. Add it manually if you need the previous behavior.
    'prefix' => 'my-app',
];

?>
