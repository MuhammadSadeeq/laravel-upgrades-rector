<?php

use Illuminate\Database\Schema\Blueprint;

function createTable()
{
    $blueprint = new Blueprint($table, $callback, $prefix);
    return $blueprint;
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

function createTable()
{
    /** Laravel 12: Blueprint constructor now requires a Connection instance as the FIRST parameter. Add the connection parameter manually: new Blueprint($connection, $table, $callback, $prefix) */
    $blueprint = new Blueprint($table, $callback, $prefix);
    return $blueprint;
}

?>