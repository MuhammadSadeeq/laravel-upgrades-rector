<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Database\Eloquent\Builder;

function deleteJoinedWithLimit(Builder $query): int
{
    return $query->join('users', 'users.id', '=', 'posts.user_id')
        ->limit(10)
        ->delete();
}

function passJoinedDelete(Builder $query): void
{
    consumeDeleteResult($query->joinSub($query, 'recent')
        ->orderByDesc('posts.created_at')
        ->delete());
}

function consumeDeleteResult(int $result): void {}
