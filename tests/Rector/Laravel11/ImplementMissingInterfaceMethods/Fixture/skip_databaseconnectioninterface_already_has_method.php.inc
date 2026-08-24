<?php

namespace App\Database;

use Illuminate\Database\ConnectionInterface;

class CustomConnection implements ConnectionInterface
{
    public function scalar(string $query, array $bindings = [], bool $useReadPdo = true): mixed
    {
        return $this->run($query, $bindings);
    }
}

?>
