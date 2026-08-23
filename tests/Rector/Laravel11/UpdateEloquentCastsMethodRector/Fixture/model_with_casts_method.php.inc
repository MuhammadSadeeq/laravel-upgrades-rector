<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    public function casts()
    {
        return $this->hasMany(MovieCast::class);
    }
}

?>
-----
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// @laravel-upgrade eloquent-casts-conflict Base Eloquent model now defines a casts() method. If this casts() method is a relationship, rename it to avoid conflicts.
class Movie extends Model
{
    public function casts()
    {
        return $this->hasMany(MovieCast::class);
    }
}

?>
