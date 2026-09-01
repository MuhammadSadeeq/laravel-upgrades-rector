<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class NamedPasswordUser extends Authenticatable
{
    public function getAuthPassword()
    {
        return $this->resolvePassword();
    }

    public function getAuthPasswordName(): string
    {
        return 'secret_hash';
    }

    private function resolvePassword(): string
    {
        return 'secret_hash';
    }
}
