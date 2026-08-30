<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class ConfiguredPasswordUser extends Authenticatable
{
    protected $authPasswordName = 'secret_hash';

    public function getAuthPassword()
    {
        return 'secret_hash';
    }
}
