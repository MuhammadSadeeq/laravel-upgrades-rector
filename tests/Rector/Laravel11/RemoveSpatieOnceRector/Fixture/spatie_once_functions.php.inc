<?php

namespace App\Fixtures\Laravel11\RemoveSpatieOnceRector;

use Spatie\Once\Cache;

class SpatieOnceExample
{
    public function example()
    {
        $result = once(function () {
            return expensive_operation();
        });
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\RemoveSpatieOnceRector;

class SpatieOnceExample
{
    public function example()
    {
        $result = once(function () {
            return expensive_operation();
        });
    }
}

?>
