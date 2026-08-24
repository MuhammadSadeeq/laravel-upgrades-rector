<?php

namespace App\Fixtures\Laravel11\RemoveSpatieOnceRector;

use Spatie\Once\Cache;
use Spatie\Once\OnceService;

class ImportExample
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

class ImportExample
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
