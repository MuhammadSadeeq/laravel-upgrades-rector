<?php

use Illuminate\Database\ConnectionInterface;

class CompleteConnection implements ConnectionInterface
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

    public function scalar($query, $bindings = [], $useReadPdo = true)
    {
        // implementation for scalar method
        return $this->selectOne($query, $bindings, $useReadPdo);
    }
}

?>