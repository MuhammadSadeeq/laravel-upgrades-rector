<?php

namespace App;

use Illuminate\Support\Facades\Schema;

function inspectStaticSchema(): void
{
    $type = Schema::getColumnType('users', 'name');
}
