<?php

namespace App\Fixtures\Laravel11\RemoveSpatieOnceRector;

use Spatie\Once\Cache;
use App\Services\MyService;

class MixedImports
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\RemoveSpatieOnceRector;

use App\Services\MyService;

class MixedImports
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
