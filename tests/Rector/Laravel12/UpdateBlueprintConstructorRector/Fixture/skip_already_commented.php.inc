<?php

use Illuminate\Database\Schema\Blueprint;

function alreadyDone()
{
    // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
    $blueprint = new Blueprint($table, $callback, $prefix);
}

?>
