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
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $blueprint->integer('votes')->nullable()->change();
}

?>
