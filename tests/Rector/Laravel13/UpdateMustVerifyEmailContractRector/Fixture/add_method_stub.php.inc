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
        // TODO: Laravel 13 — implement markEmailAsUnverified() to satisfy the updated contract.
        return false;
    }
}

?>
