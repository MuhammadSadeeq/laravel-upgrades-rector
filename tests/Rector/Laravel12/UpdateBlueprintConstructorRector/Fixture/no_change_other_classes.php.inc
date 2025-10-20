<?php

use SomeOther\Blueprint;

function createOtherBlueprint()
{
    // This should not be changed as it's not Laravel's Blueprint
    return new Blueprint($table, $callback, $prefix);
}

class CustomBlueprint
{
    public function __construct($table, $callback, $prefix)
    {
    }
}

function createCustomBlueprint()
{
    // This should not be changed as it's not Laravel's Blueprint
    return new CustomBlueprint($table, $callback, $prefix);
}