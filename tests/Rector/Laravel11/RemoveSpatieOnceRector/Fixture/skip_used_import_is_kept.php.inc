<?php

namespace App\Support;

use Spatie\Once\Cache;

final class CacheAware
{
    /**
     * @param Cache $cache
     */
    public function remember($cache): mixed
    {
        return $cache->get('key');
    }
}

?>
-----
<?php

namespace App\Support;

use Spatie\Once\Cache;

final class CacheAware
{
    /**
     * @param Cache $cache
     */
    public function remember($cache): mixed
    {
        return $cache->get('key');
    }
}

?>
