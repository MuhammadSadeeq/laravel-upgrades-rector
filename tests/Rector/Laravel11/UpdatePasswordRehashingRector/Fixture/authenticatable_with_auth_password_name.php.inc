<?php

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    protected $authPasswordName = 'admin_password';
}

?>
-----
<?php

use Illuminate\Foundation\Auth\User;

// Laravel 11: Auto password rehashing is now enabled. If using a custom password field, set protected $authPasswordName. To disable: rehash_on_login => false in config/hashing.php
class Admin extends User
{
    protected $authPasswordName = 'admin_password';
}

?>
