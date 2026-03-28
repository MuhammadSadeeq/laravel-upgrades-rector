<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SomeOther\Namespace\HasVersion7Uuids;

class Product extends Model
{
    use HasVersion7Uuids;
}
