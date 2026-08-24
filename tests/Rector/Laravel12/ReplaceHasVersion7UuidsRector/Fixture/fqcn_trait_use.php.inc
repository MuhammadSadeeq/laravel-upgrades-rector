<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

class User
{
    use \Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

class User
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
}

?>
