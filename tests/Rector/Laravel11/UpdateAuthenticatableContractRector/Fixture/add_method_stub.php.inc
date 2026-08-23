<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class CustomAuth implements Authenticatable
{
}

?>
-----
<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class CustomAuth implements Authenticatable
{
    public function getAuthPasswordName(): string
    {
        // TODO: Laravel 11 — adjust this default when the password credential column is not "password".
        return 'password';
    }
}

?>
