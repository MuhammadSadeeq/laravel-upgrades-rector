<?php

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    // This model has a casts method - should get comment
    public function casts()
    {
        return $this->hasMany(MovieCast::class);
    }

    public function director()
    {
        return $this->belongsTo(Director::class);
    }
}

class Genre extends Model
{
    // This model doesn't have a casts method - should not get comment
    protected $fillable = ['name'];

    public function movies()
    {
        return $this->belongsToMany(Movie::class);
    }
}

class RegularClass
{
    // This is not a model - should not get comment even with casts method
    public function casts()
    {
        return 'some data';
    }
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Model;

/** Laravel 11: Base Eloquent model now defines a casts() method. If this class has a casts relationship method, it may conflict. Consider renaming the relationship method to avoid conflicts. */
class Movie extends Model
{
    // This model has a casts method - should get comment
    public function casts()
    {
        return $this->hasMany(MovieCast::class);
    }

    public function director()
    {
        return $this->belongsTo(Director::class);
    }
}

class Genre extends Model
{
    // This model doesn't have a casts method - should not get comment
    protected $fillable = ['name'];

    public function movies()
    {
        return $this->belongsToMany(Movie::class);
    }
}

class RegularClass
{
    // This is not a model - should not get comment even with casts method
    public function casts()
    {
        return 'some data';
    }
}

?>