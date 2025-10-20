<?php

use Illuminate\Auth\Authenticatable;

class Admin
{
    use Authenticatable;

    protected $admin_password;
    protected $fillable = ['name', 'email'];
}

?>
-----
<?php

use Illuminate\Auth\Authenticatable;

/** Laravel 11: Auto password rehashing enabled. If using custom password field name, add protected $authPasswordName = 'custom_field_name'; To disable: set rehash_on_login => false in config/hashing.php */
class Admin
{
    use Authenticatable;

    protected $admin_password;
    protected $fillable = ['name', 'email'];
}

?>