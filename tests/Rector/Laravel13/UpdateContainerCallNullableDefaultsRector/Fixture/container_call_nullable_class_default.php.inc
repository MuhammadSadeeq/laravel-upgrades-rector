<?php

use Carbon\Carbon;
use Illuminate\Container\Container;

function resolveDate(Container $container)
{
    $container->call(function (?Carbon $date = null) {
        return $date;
    });
}

?>
-----
<?php

use Carbon\Carbon;
use Illuminate\Container\Container;

function resolveDate(Container $container)
{
    // Laravel 13: Container::call() now preserves nullable class defaults. Review any logic that expected an auto-resolved instance here.
    $container->call(function (?Carbon $date = null) {
        return $date;
    });
}

?>
