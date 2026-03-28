<?php

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
