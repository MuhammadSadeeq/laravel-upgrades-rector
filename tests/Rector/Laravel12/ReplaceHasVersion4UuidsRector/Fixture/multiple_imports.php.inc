<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion4UuidsRector;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasUuids;
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion4UuidsRector;

use Illuminate\Database\Eloquent\Model;
// Laravel 12: HasUuids now generates UUIDv7. Switch to HasVersion4Uuids if you need the previous ordered UUIDv4 behavior.
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasUuids;
}

?>
