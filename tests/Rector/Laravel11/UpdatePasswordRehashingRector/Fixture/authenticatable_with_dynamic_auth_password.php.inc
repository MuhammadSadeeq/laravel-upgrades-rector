<?php

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

use Illuminate\Foundation\Auth\User;

// Laravel 11: Auto password rehashing may require authPasswordName. This model overrides getAuthPassword(); if the password column is not "password", set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php
class Admin extends User
{
    public function getAuthPassword(): string
    {
        return $this->resolvePassword();
    }
}

?>
