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
    public function scalar(string $query, array $bindings = [], bool $useReadPdo = true): mixed
    {
        // TODO: Implement scalar() method.
        return null;
    }
}

?>
