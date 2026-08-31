<?php

namespace App\MorphPivotFixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class AuthorPostPivot extends Pivot {}

function morphRelationFor(Model $model): mixed
{
    return registerMorphRelation(
        $model->morphedByMany(AuthorPost::class, 'post')
            ->withPivot('kind')
            ->withTimestamps()
            ->using(AuthorPostPivot::class)
    );
}

function registerMorphRelation(mixed $relation): mixed
{
    return $relation;
}

final class AuthorPost extends Model {}
