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
        return 'password';
    }
}

?>
