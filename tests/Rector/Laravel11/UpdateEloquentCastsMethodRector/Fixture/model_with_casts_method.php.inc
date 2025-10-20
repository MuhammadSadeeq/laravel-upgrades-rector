<?php

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Model;

/** Laravel 11: Base Eloquent model now defines a casts() method. If this class has a casts relationship method, it may conflict. Consider renaming the relationship method to avoid conflicts. */
class User extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

?>