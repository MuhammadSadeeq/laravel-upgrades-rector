<?php

namespace App\Fixtures\Laravel11\UpdatePasswordRehashingRector;

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    public function getAuthPassword(): string
    {
        return $this->resolvePassword();
    }
}
