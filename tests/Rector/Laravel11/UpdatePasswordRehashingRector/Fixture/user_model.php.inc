<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];
}

?>
-----
<?php

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Laravel 11: Auto password rehashing enabled. If using custom password field name, add protected $authPasswordName = 'custom_field_name'; To disable: set rehash_on_login => false in config/hashing.php */
class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];
}

?>