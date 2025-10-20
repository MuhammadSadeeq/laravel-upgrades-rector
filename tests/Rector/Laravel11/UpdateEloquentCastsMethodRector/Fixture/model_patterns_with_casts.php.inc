<?php

// Class names that match common Eloquent patterns
class UserModel
{
    public function casts()
    {
        return $this->hasMany(UserCast::class);
    }
}

class Post
{
    public function casts()
    {
        return $this->morphMany(Cast::class, 'castable');
    }
}

class OrderModel
{
    public function casts()
    {
        return $this->belongsToMany(Cast::class);
    }
}

?>
-----
<?php

/** Laravel 11: Base Eloquent model now defines a casts() method. If this class has a casts relationship method, it may conflict. Consider renaming the relationship method to avoid conflicts. */
class UserModel
{
    public function casts()
    {
        return $this->hasMany(UserCast::class);
    }
}

/** Laravel 11: Base Eloquent model now defines a casts() method. If this class has a casts relationship method, it may conflict. Consider renaming the relationship method to avoid conflicts. */
class Post
{
    public function casts()
    {
        return $this->morphMany(Cast::class, 'castable');
    }
}

/** Laravel 11: Base Eloquent model now defines a casts() method. If this class has a casts relationship method, it may conflict. Consider renaming the relationship method to avoid conflicts. */
class OrderModel
{
    public function casts()
    {
        return $this->belongsToMany(Cast::class);
    }
}

?>