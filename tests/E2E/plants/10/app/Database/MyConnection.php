<?php

namespace App\Database;

use Closure;
use Illuminate\Database\ConnectionInterface;

class MyConnection implements ConnectionInterface
{
    public function table($table, $as = null)
    {
        return true;
    }

    public function raw($value)
    {
        return $value;
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        return [];
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return [];
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        return [];
    }

    public function insert($query, $bindings = [])
    {
        return true;
    }

    public function update($query, $bindings = [])
    {
        return 1;
    }

    public function delete($query, $bindings = [])
    {
        return 1;
    }

    public function statement($query, $bindings = [])
    {
        return true;
    }

    public function affectingStatement($query, $bindings = [])
    {
        return 1;
    }

    public function unprepared($query)
    {
        return true;
    }

    public function prepareBindings(array $bindings)
    {
        return $bindings;
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        return $callback($this);
    }

    public function beginTransaction()
    {
    }

    public function rollBack()
    {
    }

    public function commit()
    {
    }

    public function transactionLevel()
    {
        return 0;
    }

    public function pretend(Closure $callback)
    {
        return [];
    }

    public function getDatabaseName()
    {
        return 'e2e';
    }
}
