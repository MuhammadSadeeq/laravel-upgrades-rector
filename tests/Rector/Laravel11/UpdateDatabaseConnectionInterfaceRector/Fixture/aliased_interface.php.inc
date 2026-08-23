<?php

use Illuminate\Database\ConnectionInterface as ConnectionContract;

class LegacyConnection implements ConnectionContract
{
}

?>
-----
<?php

use Illuminate\Database\ConnectionInterface as ConnectionContract;

class LegacyConnection implements ConnectionContract
{
    public function scalar($query, $bindings = [], $useReadPdo = true): mixed
    {
        // TODO: Laravel 11 — implement scalar() to satisfy the updated contract.
        return null;
    }
}

?>
