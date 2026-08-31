<?php

namespace App\MorphPivotFixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

final class PostTagPivot extends MorphPivot {}

final class Post extends Model
{
    public function tags(): mixed
    {
        return $this->morphToMany(Tag::class, 'tag')->using(PostTagPivot::class);
    }
}

final class Tag extends Model {}
