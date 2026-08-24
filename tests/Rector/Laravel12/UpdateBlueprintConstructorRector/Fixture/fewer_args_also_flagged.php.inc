<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

function fewerArgs()
{
    $bp1 = new Blueprint($table);
    $bp2 = new Blueprint($table, $callback);
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

function fewerArgs()
{
    // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
    $bp1 = new Blueprint($table);
    // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
    $bp2 = new Blueprint($table, $callback);
}

?>
