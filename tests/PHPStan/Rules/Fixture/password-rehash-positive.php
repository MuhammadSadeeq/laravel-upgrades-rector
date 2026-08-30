<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class LegacyUser extends Authenticatable
{
    public function getAuthPassword()
    {
        return 'secret_hash';
    }
}
