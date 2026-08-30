<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class DefaultPasswordUser extends Authenticatable
{
    public function getAuthPassword()
    {
        return 'password';
    }
}
