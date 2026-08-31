<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

final class StaticBootedModel extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        new static;
    }

    protected static function bootWithTraits(): void
    {
        new self;
    }
}
