<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Database\Query\Builder;

function upsertWithEmptyUniqueBy(Builder $builder): int
{
    return $builder->upsert([['email' => 'a@example.test']], [], ['name']);
}
