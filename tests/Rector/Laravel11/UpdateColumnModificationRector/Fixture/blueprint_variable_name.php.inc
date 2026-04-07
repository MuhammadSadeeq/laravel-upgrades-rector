<?php

use Illuminate\Database\Schema\Blueprint;

function updateColumn(Blueprint $blueprint)
{
    $blueprint->integer('votes')->nullable()->change();
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

function updateColumn(Blueprint $blueprint)
{
    // Laravel 11: change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $blueprint->integer('votes')->nullable()->change();
}

?>
