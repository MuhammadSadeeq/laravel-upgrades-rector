<?php

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected static function boot()
    {
        parent::boot();

        (new static())->getTable();
    }
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // @laravel-upgrade eloquent-behavior instantiating models during boot() is now disallowed. Move this logic outside the model boot cycle.
    protected static function boot()
    {
        parent::boot();

        (new static())->getTable();
    }
}

?>
