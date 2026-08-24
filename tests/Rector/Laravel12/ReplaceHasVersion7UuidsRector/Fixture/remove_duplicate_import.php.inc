<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class AuditEvent
{
    use HasVersion7Uuids;
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditEvent
{
    use HasUuids;
}

?>
