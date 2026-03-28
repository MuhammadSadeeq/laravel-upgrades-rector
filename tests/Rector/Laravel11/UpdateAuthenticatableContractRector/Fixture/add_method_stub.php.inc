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
        return 'password';
    }
}

?>
