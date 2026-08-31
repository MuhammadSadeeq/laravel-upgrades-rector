<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Database\Query\Builder;

final class UpsertUnrelatedService
{
    public function upsert(array $values, array $uniqueBy): int
    {
        return count($values) + count($uniqueBy);
    }
}

function upsertWithValues(Builder $builder, array $uniqueBy): int
{
    $builder->upsert([['email' => 'a@example.test']], ['email'], ['name']);

    return $builder->upsert([['email' => 'a@example.test']], $uniqueBy, ['name']);
}

function upsertWithoutUniqueBy(Builder $builder): int
{
    return $builder->upsert([['email' => 'a@example.test']]);
}

function unrelatedUpsert(UpsertUnrelatedService $service): int
{
    return $service->upsert([], []);
}
