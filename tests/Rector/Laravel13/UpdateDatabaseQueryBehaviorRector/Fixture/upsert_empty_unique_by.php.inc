<?php

use Illuminate\Database\Query\Builder;

function runUpsert(Builder $query, array $rows)
{
    $query->upsert($rows, [], ['name']);
}

?>
-----
<?php

use Illuminate\Database\Query\Builder;

function runUpsert(Builder $query, array $rows)
{
    // Laravel 13: upsert() now throws InvalidArgumentException when uniqueBy is empty on MySQL or MariaDB. Pass a non-empty uniqueBy value.
    $query->upsert($rows, [], ['name']);
}

?>
