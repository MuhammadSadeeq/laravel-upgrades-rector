<?php

namespace App\Fixtures\Laravel11\UpdatePasswordRehashingRector;

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    public function getAuthPassword(): string
    {
        return $this->resolvePassword();
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdatePasswordRehashingRector;

use Illuminate\Foundation\Auth\User;

// @laravel-upgrade password-rehashing This model overrides getAuthPassword(); if the password column is not "password", set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php.
class Admin extends User
{
    public function getAuthPassword(): string
    {
        return $this->resolvePassword();
    }
}

?>
