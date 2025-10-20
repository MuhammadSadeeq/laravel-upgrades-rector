<?php

use Illuminate\Contracts\Auth\Authenticatable;
use SomeOther\Interface as SomeInterface;

class MultipleInterfaces implements SomeInterface, Authenticatable
{
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function someInterfaceMethod()
    {
        return 'implementation';
    }
}

?>
-----
<?php

use Illuminate\Contracts\Auth\Authenticatable;
use SomeOther\Interface as SomeInterface;

/** Laravel 11: Authenticatable contract requires new getAuthPasswordName method. Add: public function getAuthPasswordName() { return 'password'; } */
class MultipleInterfaces implements SomeInterface, Authenticatable
{
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function someInterfaceMethod()
    {
        return 'implementation';
    }
}

?>