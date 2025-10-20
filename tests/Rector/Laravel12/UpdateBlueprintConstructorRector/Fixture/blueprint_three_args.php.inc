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
    $blueprint = new Blueprint($connection, $table, $callback, $prefix);
    return $blueprint;
}

?>