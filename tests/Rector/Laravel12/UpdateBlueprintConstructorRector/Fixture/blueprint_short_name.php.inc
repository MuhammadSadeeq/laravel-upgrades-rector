<?php

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

class MyClass
{
    public function createBlueprint()
    {
        // Laravel 12: Blueprint constructor now requires $connection as the first parameter.
        $bp = new Blueprint($table, $callback, $prefix);
    }
}

?>
