<?php

namespace App\Fixtures\Laravel11\UpdateDatabaseConnectionInterfaceRector; // @loads

use Illuminate\Database\ConnectionInterface as ConnectionContract;

class LegacyConnection implements ConnectionContract
{
    public function table($table, $as = null)
    {
    }

    public function raw($value)
    {
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
    }

    public function insert($query, $bindings = [])
    {
    }

    public function update($query, $bindings = [])
    {
    }

    public function delete($query, $bindings = [])
    {
    }

    public function statement($query, $bindings = [])
    {
    }

    public function affectingStatement($query, $bindings = [])
    {
    }

    public function unprepared($query)
    {
    }

    public function prepareBindings(array $bindings)
    {
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
    }

    public function beginTransaction()
    {
    }

    public function commit()
    {
    }

    public function rollBack()
    {
    }

    public function transactionLevel()
    {
    }

    public function pretend(Closure $callback)
    {
    }

    public function getDatabaseName()
    {
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateDatabaseConnectionInterfaceRector; // @loads

use Illuminate\Database\ConnectionInterface as ConnectionContract;

class LegacyConnection implements ConnectionContract
{
    public function table($table, $as = null)
    {
    }

    public function raw($value)
    {
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
    }

    public function insert($query, $bindings = [])
    {
    }

    public function update($query, $bindings = [])
    {
    }

    public function delete($query, $bindings = [])
    {
    }

    public function statement($query, $bindings = [])
    {
    }

    public function affectingStatement($query, $bindings = [])
    {
    }

    public function unprepared($query)
    {
    }

    public function prepareBindings(array $bindings)
    {
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
    }

    public function beginTransaction()
    {
    }

    public function commit()
    {
    }

    public function rollBack()
    {
    }

    public function transactionLevel()
    {
    }

    public function pretend(Closure $callback)
    {
    }

    public function getDatabaseName()
    {
    }
    public function scalar($query, $bindings = [], $useReadPdo = true): mixed
    {
        // TODO: Laravel 11 — implement scalar() to satisfy the updated contract.
        return null;
    }
}

?>
