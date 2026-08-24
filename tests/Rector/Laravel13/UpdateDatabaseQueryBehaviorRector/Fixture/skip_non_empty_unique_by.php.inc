<?php

namespace App\Fixtures\Laravel13\UpdateDatabaseQueryBehaviorRector;

use Illuminate\Database\Query\Builder;

function runUpsert(Builder $query, array $rows)
{
    $query->upsert($rows, ['email'], ['name']);
}

?>
