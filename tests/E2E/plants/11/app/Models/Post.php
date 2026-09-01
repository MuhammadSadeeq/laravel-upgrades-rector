<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    use HasUuids;

    /** Eloquent's dynamic date attribute remains intentionally untyped. */
    public function ageInDays(): mixed
    {
        return $this->created_at->diffInDays(now());
    }
}
