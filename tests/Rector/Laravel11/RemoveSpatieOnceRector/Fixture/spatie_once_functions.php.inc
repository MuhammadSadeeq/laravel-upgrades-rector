<?php

use Spatie\Once\Cache;

class SpatieOnceExample
{
    public function example()
    {
        $result = spatieOnce(function () {
            return expensive_operation();
        });

        $value = onceWithSpatie(function () {
            return another_operation();
        });
    }
}

?>
-----
<?php

use Spatie\Once\Cache;

class SpatieOnceExample
{
    public function example()
    {
        $result = spatieOnce(function () {
            return expensive_operation();
        });

        $value = onceWithSpatie(function () {
            return another_operation();
        });
    }
}

?>