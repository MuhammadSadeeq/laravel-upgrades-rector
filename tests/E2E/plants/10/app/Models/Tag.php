<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    /**
     * Laravel 11 adds Model::casts(), so this legacy relationship must be
     * renamed and its callers updated before the application can upgrade.
     */
    public function casts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
