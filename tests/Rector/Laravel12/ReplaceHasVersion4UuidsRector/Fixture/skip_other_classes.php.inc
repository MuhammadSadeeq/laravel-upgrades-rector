<?php

use Illuminate\Database\Eloquent\Concerns\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;

class Product
{
    use SoftDeletes;
    use HasTimestamps;
}

?>
