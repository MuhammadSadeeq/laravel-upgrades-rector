<?php

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class User
{
    use HasVersion7Uuids;
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User
{
    use HasUuids;
}

?>
