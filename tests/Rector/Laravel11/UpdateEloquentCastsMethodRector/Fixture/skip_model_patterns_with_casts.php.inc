<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public function casts()
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}

?>
