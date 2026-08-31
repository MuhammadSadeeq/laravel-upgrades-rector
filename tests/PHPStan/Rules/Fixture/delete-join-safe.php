<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Database\Query\Builder;

final class DeleteUnrelatedService
{
    public function join(string $table): self
    {
        return $this;
    }

    public function orderBy(string $column): self
    {
        return $this;
    }

    public function delete(): int
    {
        return 0;
    }
}

function deleteWithoutOrdering(Builder $query): int
{
    return $query->join('users', 'users.id', '=', 'posts.user_id')->delete();
}

function deleteWithoutJoin(Builder $query): int
{
    return $query->orderBy('posts.created_at')->limit(10)->delete();
}

function unrelatedDeleteChain(DeleteUnrelatedService $service): int
{
    return $service->join('users')->orderBy('id')->delete();
}
