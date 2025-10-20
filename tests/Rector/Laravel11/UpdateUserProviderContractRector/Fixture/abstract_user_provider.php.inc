<?php

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class BaseUserProvider implements UserProvider
{
    public function retrieveByToken($identifier, $token)
    {
        // implementation
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // implementation
    }

    abstract public function retrieveById($identifier);

    abstract public function retrieveByCredentials(array $credentials);

    abstract public function validateCredentials(Authenticatable $user, array $credentials);
}

?>
-----
<?php

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/** Laravel 11: UserProvider contract requires new rehashPasswordIfRequired method. Add: public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false); */
abstract class BaseUserProvider implements UserProvider
{
    public function retrieveByToken($identifier, $token)
    {
        // implementation
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // implementation
    }

    abstract public function retrieveById($identifier);

    abstract public function retrieveByCredentials(array $credentials);

    abstract public function validateCredentials(Authenticatable $user, array $credentials);
}

?>