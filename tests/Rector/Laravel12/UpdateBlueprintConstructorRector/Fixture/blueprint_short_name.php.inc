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
        return new Blueprint($connection, $table, $callback, $prefix);
    }
}

?>