<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

final class BootedModel extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        new BootedModel;
    }

    protected static function bootWithTraits(): void
    {
        new static;
    }
}

function consumeBootModel(Model $model): void {}
