<?php

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
