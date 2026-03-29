<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
}

?>
-----
<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
    public function markEmailAsUnverified(): bool
    {
        return false;
    }
}

?>
