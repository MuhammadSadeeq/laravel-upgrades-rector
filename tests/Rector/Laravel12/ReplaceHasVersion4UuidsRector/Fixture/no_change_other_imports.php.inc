<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SomeOther\Namespace\HasUuids;

class Product extends Model
{
    use HasUuids;
}