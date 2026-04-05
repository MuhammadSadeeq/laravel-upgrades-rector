<?php

namespace App\Cache;

use Illuminate\Contracts\Cache\Repository;

class CustomRepository implements Repository
{
}

?>
-----
<?php

namespace App\Cache;

use Illuminate\Contracts\Cache\Repository;

class CustomRepository implements Repository
{
    public function touch(string $key, int $seconds): bool
    {
        return false;
    }
}

?>
