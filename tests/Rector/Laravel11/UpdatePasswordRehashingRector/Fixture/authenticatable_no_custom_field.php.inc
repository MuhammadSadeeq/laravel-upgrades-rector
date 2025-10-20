<?php

use Illuminate\Auth\Authenticatable;

class Account
{
    use Authenticatable;

    protected $fillable = ['name', 'email'];
    protected $hidden = ['password'];
}

?>