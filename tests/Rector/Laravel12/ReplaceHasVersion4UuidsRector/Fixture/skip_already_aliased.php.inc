<?php

namespace App\Fixtures\Laravel12\ReplaceHasVersion4UuidsRector;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;

class User
{
    use HasUuids;
}
