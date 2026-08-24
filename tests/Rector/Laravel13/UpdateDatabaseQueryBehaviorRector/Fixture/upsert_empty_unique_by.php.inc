<?php

namespace App\Fixtures\Laravel13\UpdateDatabaseQueryBehaviorRector;

use Illuminate\Database\Query\Builder;

function runUpsert(Builder $query, array $rows)
{
    $query->upsert($rows, [], ['name']);
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateDatabaseQueryBehaviorRector;

use Illuminate\Database\Query\Builder;

function runUpsert(Builder $query, array $rows)
{
    // @laravel-upgrade db-query-behavior upsert() now throws InvalidArgumentException when uniqueBy is empty on MySQL or MariaDB. Pass a non-empty uniqueBy value.
    $query->upsert($rows, [], ['name']);
}

?>
