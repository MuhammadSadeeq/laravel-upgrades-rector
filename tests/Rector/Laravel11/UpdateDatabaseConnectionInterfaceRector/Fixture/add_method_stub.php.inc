<?php

namespace App\Database;

use Illuminate\Database\ConnectionInterface;

class CustomConnection implements ConnectionInterface
{
}

?>
-----
<?php

namespace App\Database;

use Illuminate\Database\ConnectionInterface;

class CustomConnection implements ConnectionInterface
{
    public function scalar($query, $bindings = [], $useReadPdo = true): mixed
    {
        // TODO: Laravel 11 — implement scalar() to satisfy the updated contract.
        return null;
    }
}

?>
