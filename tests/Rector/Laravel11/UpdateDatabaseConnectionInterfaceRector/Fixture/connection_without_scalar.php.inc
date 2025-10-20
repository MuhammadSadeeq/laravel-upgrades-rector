<?php

use Illuminate\Database\ConnectionInterface;

class CustomConnection implements ConnectionInterface
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
}

?>
-----
<?php

use Illuminate\Database\ConnectionInterface;

/** Laravel 11: ConnectionInterface requires new scalar method. Add: public function scalar($query, $bindings = [], $useReadPdo = true); */
class CustomConnection implements ConnectionInterface
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
}

?>