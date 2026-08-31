<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Database\Query\Builder;

function deleteJoinedInOrder(Builder $query): int
{
    return $query->join('users', 'users.id', '=', 'posts.user_id')
        ->orderBy('posts.created_at')
        ->delete();
}
