<?php

use Illuminate\Database\ConnectionInterface;

abstract class AbstractConnection implements ConnectionInterface
{
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // base implementation
    }

    public function insert($query, $bindings = [])
    {
        // base implementation
    }

    abstract public function update($query, $bindings = []);

    abstract public function delete($query, $bindings = []);

    abstract public function statement($query, $bindings = []);

    abstract public function affectingStatement($query, $bindings = []);
}

?>
-----
<?php

use Illuminate\Database\ConnectionInterface;

/** Laravel 11: ConnectionInterface requires new scalar method. Add: public function scalar($query, $bindings = [], $useReadPdo = true); */
abstract class AbstractConnection implements ConnectionInterface
{
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // base implementation
    }

    public function insert($query, $bindings = [])
    {
        // base implementation
    }

    abstract public function update($query, $bindings = []);

    abstract public function delete($query, $bindings = []);

    abstract public function statement($query, $bindings = []);

    abstract public function affectingStatement($query, $bindings = []);
}

?>