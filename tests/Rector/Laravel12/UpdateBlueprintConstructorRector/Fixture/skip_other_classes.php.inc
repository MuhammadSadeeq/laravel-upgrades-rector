<?php

namespace App\Fixtures\Laravel12\UpdateBlueprintConstructorRector;

class CustomBlueprint
{
    public function __construct($table, $callback, $prefix)
    {
    }
}

function createCustomBlueprint()
{
    $bp = new CustomBlueprint($table, $callback, $prefix);
}

?>
