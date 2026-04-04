<?php

class User
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
}

?>
-----
<?php

class User
{
    // Laravel 12: HasUuids now generates UUIDv7. Switch to HasVersion4Uuids if you need the previous ordered UUIDv4 behavior.
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
}

?>
