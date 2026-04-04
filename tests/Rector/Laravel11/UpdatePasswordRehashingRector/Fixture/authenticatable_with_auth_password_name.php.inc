<?php

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    protected $authPasswordName = 'admin_password';
}

?>
