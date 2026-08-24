<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

use Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;

class AuditEvent extends Model
{
    use HasVersion7Uuids;
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion7UuidsRector;

use Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditEvent extends Model
{
    use HasUuids;
}

?>
