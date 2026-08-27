<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public function casts()
    {
        return $this->hasMany(self::class);
    }
}
