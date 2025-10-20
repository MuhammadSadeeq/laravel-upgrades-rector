<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User
{
    use HasUuids;
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;

class User
{
    use HasUuids;
}

?>