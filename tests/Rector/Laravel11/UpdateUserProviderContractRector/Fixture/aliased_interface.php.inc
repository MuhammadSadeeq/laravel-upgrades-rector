<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;

class LegacyUserProvider implements UserProviderContract
{
    public function retrieveById($identifier)
    {
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
    }

    public function updateRememberToken(AuthenticatableContract $user, #[\SensitiveParameter] $token)
    {
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
    }

    public function validateCredentials(AuthenticatableContract $user, #[\SensitiveParameter] array $credentials)
    {
    }
}

?>
-----
<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;

class LegacyUserProvider implements UserProviderContract
{
    public function retrieveById($identifier)
    {
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
    }

    public function updateRememberToken(AuthenticatableContract $user, #[\SensitiveParameter] $token)
    {
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
    }

    public function validateCredentials(AuthenticatableContract $user, #[\SensitiveParameter] array $credentials)
    {
    }
    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false): void
    {
        // TODO: Implement rehashPasswordIfRequired() method.
    }
}

?>
