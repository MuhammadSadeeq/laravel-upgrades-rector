<?php

use Illuminate\Database\Schema\Blueprint;

function fewerArgs()
{
    $bp1 = new Blueprint($table);
    $bp2 = new Blueprint($table, $callback);
}

?>
