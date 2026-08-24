<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class LegacyAuthenticatable implements AuthenticatableContract
{
    public function getAuthIdentifierName()
    {
    }

    public function getAuthIdentifier()
    {
    }

    public function getAuthPassword()
    {
    }

    public function getRememberToken()
    {
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
    }
}

?>
-----
<?php // @loads

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class LegacyAuthenticatable implements AuthenticatableContract
{
    public function getAuthIdentifierName()
    {
    }

    public function getAuthIdentifier()
    {
    }

    public function getAuthPassword()
    {
    }

    public function getRememberToken()
    {
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
    }
    public function getAuthPasswordName(): string
    {
        // TODO: Laravel 11 — adjust this default when the password credential column is not "password".
        return 'password';
    }
}

?>
