<?php

namespace App\Fixtures\Laravel11\UpdatePasswordRehashingRector;

use Illuminate\Foundation\Auth\User;

class NamedPasswordUser extends User
{
    public function getAuthPassword(): string
    {
        return $this->resolvePassword();
    }

    public function getAuthPasswordName(): string
    {
        return 'admin_password';
    }

    private function resolvePassword(): string
    {
        return 'admin_password';
    }
}
