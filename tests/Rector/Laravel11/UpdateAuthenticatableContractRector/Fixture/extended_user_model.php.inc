<?php

use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Contracts\Auth\Authenticatable;

class ExtendedUser extends BaseUser implements Authenticatable
{
    protected $fillable = ['name', 'email'];

    public function customMethod()
    {
        return 'custom';
    }
}

?>
-----
<?php

use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Contracts\Auth\Authenticatable;

/** Laravel 11: Authenticatable contract requires new getAuthPasswordName method. Add: public function getAuthPasswordName() { return 'password'; } */
class ExtendedUser extends BaseUser implements Authenticatable
{
    protected $fillable = ['name', 'email'];

    public function customMethod()
    {
        return 'custom';
    }
}

?>