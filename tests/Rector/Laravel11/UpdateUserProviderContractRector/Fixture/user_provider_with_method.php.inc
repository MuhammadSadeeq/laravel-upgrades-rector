<?php

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class ModernUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        // implementation
    }

    public function retrieveByToken($identifier, $token)
    {
        // implementation
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // implementation
    }

    public function retrieveByCredentials(array $credentials)
    {
        // implementation
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        // implementation
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        // implementation
    }
}

?>