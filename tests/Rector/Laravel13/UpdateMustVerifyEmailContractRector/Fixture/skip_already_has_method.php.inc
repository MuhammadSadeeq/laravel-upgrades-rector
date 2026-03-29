<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
    public function markEmailAsUnverified(): bool
    {
        return true;
    }
}

?>
