<?php

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

interface CustomInterface
{
    public function custom();
}

class MultiInterfaceProvider implements CustomInterface, UserProvider
{
    public function custom()
    {
        // implementation
    }

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
}

?>
-----
<?php

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

interface CustomInterface
{
    public function custom();
}

/** Laravel 11: UserProvider contract requires new rehashPasswordIfRequired method. Add: public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false); */
class MultiInterfaceProvider implements CustomInterface, UserProvider
{
    public function custom()
    {
        // implementation
    }

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
}

?>