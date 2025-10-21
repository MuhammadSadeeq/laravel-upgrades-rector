<?php

class MyClass
{
    public function createBlueprint()
    {
        return new Blueprint($table, $callback, $prefix);
    }
}

?>
-----
<?php

class MyClass
{
    public function createBlueprint()
    {
        /** Laravel 12: Blueprint constructor now requires a Connection instance as the FIRST parameter. Add the connection parameter manually: new Blueprint($connection, $table, $callback, $prefix) */
        return new Blueprint($table, $callback, $prefix);
    }
}

?>