<?php

namespace App\Fixtures\Laravel11\UpdateEloquentCastsMethodRector;

use Illuminate\Database\Eloquent\Model;

class LegacyCastsMethodUsage extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateEloquentCastsMethodRector;

use Illuminate\Database\Eloquent\Model;

// @laravel-upgrade eloquent-casts-conflict Base Eloquent model now defines a casts() method. If this casts() method is a relationship, rename it to avoid conflicts.
class LegacyCastsMethodUsage extends Model
{
    public function casts()
    {
        return $this->hasMany(Cast::class);
    }
}

?>
