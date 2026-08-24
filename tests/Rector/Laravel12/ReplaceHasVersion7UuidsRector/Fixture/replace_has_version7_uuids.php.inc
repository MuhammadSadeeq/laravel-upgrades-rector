<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class User
{
    use HasVersion7Uuids;
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User
{
    use HasUuids;
}

?>
