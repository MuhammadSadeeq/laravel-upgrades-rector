<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

final class SafeBootedModel extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        new stdClass;
    }

    public static function make(): self
    {
        return new self;
    }
}

final class NonModelBootHolder
{
    protected static function boot(): void
    {
        new stdClass;
    }
}
