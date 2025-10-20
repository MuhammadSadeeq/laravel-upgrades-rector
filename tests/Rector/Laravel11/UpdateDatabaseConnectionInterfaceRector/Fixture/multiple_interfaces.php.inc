<?php

use Illuminate\Database\ConnectionInterface;
use SomeOther\Interface as SomeInterface;

class MultipleInterfacesConnection implements SomeInterface, ConnectionInterface
{
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // implementation
    }

    public function insert($query, $bindings = [])
    {
        // implementation
    }

    public function update($query, $bindings = [])
    {
        // implementation
    }

    public function delete($query, $bindings = [])
    {
        // implementation
    }

    public function statement($query, $bindings = [])
    {
        // implementation
    }

    public function affectingStatement($query, $bindings = [])
    {
        // implementation
    }

    public function someInterfaceMethod()
    {
        // implementation for other interface
    }
}

?>
-----
<?php

use Illuminate\Database\ConnectionInterface;
use SomeOther\Interface as SomeInterface;

/** Laravel 11: ConnectionInterface requires new scalar method. Add: public function scalar($query, $bindings = [], $useReadPdo = true); */
class MultipleInterfacesConnection implements SomeInterface, ConnectionInterface
{
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // implementation
    }

    public function insert($query, $bindings = [])
    {
        // implementation
    }

    public function update($query, $bindings = [])
    {
        // implementation
    }

    public function delete($query, $bindings = [])
    {
        // implementation
    }

    public function statement($query, $bindings = [])
    {
        // implementation
    }

    public function affectingStatement($query, $bindings = [])
    {
        // implementation
    }

    public function someInterfaceMethod()
    {
        // implementation for other interface
    }
}

?>