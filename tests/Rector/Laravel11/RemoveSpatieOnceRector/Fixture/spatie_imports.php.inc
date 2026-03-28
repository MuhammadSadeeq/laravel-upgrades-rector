<?php

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

class ImportExample
{
    public function example()
    {
        $result = once(fn() => expensive());
    }
}

?>
