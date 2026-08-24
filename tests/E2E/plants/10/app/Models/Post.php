<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasUuids;

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function ageInDays(): float
    {
        return $this->created_at->diffInDays(now());
    }

    public function hoursUntil(\Illuminate\Support\Carbon $then): float
    {
        return $this->created_at->diffInHours($then, false);
    }
}
