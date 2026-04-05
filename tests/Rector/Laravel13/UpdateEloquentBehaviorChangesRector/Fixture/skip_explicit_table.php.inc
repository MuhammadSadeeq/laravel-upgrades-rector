<?php

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class TaggablePivot extends MorphPivot
{
    protected string $table = 'taggable_pivot';
}

?>
