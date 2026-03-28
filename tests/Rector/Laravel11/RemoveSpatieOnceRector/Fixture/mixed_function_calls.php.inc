<?php

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

use App\Services\MyService;

class MixedImports
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
