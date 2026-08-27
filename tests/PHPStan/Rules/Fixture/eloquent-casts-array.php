<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SafeCastModel extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
