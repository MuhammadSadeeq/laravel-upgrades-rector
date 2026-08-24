<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class CustomUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token)
    {
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
    }
}

?>
-----
<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class CustomUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token)
    {
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
    }
    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false): void
    {
        // TODO: Implement rehashPasswordIfRequired() method.
    }
}

?>
