<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class AuditEvent
{
    use HasVersion7Uuids;
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditEvent
{
    use HasUuids;
}

?>
