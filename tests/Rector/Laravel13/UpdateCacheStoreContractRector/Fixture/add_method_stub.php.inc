<?php

namespace App\Cache;

use Illuminate\Contracts\Cache\Store;

class CustomStore implements Store
{
}

?>
-----
<?php

namespace App\Cache;

use Illuminate\Contracts\Cache\Store;

class CustomStore implements Store
{
    public function touch(string $key, int $seconds): bool
    {
        return false;
    }
}

?>
