<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Database\Eloquent\Builder;

function upsertWithEmptyString(Builder $builder): int
{
    return $builder->upsert([['email' => 'a@example.test']], '', ['name']);
}

function wrapUpsertWithNamedUniqueBy(Builder $builder): mixed
{
    return wrapUpsert($builder->upsert(
        values: [['email' => 'a@example.test']],
        uniqueBy: [],
        update: ['name'],
    ));
}

function wrapUpsert(mixed $value): mixed
{
    return $value;
}
