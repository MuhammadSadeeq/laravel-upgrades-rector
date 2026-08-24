<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

function createTable()
{
    $blueprint = new Blueprint($table, $callback, $prefix);
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

function createTable()
{
    // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
    $blueprint = new Blueprint($table, $callback, $prefix);
}

?>
