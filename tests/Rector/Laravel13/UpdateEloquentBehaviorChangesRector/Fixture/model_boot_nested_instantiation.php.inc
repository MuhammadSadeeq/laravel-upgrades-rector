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
    // Laravel 13: instantiating models during boot() is now disallowed. Move this logic outside the model boot cycle.
    protected static function boot()
    {
        parent::boot();
        (new static())->getTable();
    }
}

?>
