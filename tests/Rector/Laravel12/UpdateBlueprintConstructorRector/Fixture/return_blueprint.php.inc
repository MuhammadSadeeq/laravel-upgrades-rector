<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

function createBlueprint()
{
    return new Blueprint($table, $callback, $prefix);
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

function createBlueprint()
{
    // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
    return new Blueprint($table, $callback, $prefix);
}

?>
