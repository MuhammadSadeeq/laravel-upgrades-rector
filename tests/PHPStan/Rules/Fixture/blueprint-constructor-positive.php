<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function blueprintNeedsConnection(string $table): void
{
    new Blueprint($table, null);
}
