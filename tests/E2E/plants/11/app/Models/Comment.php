<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $fillable = ['body'];
}
