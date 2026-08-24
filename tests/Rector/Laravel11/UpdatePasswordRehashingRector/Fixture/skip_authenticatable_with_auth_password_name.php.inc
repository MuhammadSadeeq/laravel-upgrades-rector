<?php

namespace App\Fixtures\Laravel11\UpdatePasswordRehashingRector;

use Illuminate\Foundation\Auth\User;

class Admin extends User
{
    protected $authPasswordName = 'admin_password';
}

?>
