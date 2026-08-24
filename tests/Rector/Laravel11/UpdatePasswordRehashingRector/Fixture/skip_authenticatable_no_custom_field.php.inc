<?php

namespace App\Fixtures\Laravel11\UpdatePasswordRehashingRector;

class Account
{
    protected $fillable = ['name', 'email'];
    protected $hidden = ['password'];
}

?>
