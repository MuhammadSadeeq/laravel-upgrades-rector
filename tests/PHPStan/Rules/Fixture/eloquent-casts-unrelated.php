<?php

namespace App;

class PlainModel
{
    public function casts()
    {
        return $this->hasMany(self::class);
    }
}
