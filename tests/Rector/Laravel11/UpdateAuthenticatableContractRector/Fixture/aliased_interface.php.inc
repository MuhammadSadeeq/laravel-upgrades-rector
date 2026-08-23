<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class LegacyAuthenticatable implements AuthenticatableContract
{
}

?>
-----
<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class LegacyAuthenticatable implements AuthenticatableContract
{
    public function getAuthPasswordName(): string
    {
        // TODO: Laravel 11 — adjust this default when the password credential column is not "password".
        return 'password';
    }
}

?>
