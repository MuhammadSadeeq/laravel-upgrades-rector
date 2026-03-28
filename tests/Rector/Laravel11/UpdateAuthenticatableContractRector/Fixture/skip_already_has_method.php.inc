<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class CustomAuth implements Authenticatable
{
    public function getAuthPasswordName(): string
    {
        return 'custom_password';
    }
}

?>
