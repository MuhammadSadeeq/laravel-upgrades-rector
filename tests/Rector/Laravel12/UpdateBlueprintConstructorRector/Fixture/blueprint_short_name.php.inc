<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

class MyClass
{
    public function createBlueprint()
    {
        $bp = new Blueprint($table, $callback, $prefix);
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

use Illuminate\Database\Schema\Blueprint;

class MyClass
{
    public function createBlueprint()
    {
        // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
        $bp = new Blueprint($table, $callback, $prefix);
    }
}

?>
