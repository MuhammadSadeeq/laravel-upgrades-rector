<?php

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    public function getAuthPassword(): string
    {
        return $this->admin_password;
    }
}

?>
-----
<?php

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    protected $authPasswordName = 'admin_password';
    public function getAuthPassword(): string
    {
        return $this->admin_password;
    }
}

?>
