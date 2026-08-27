<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function blueprintUnknownConnection($connection, string $table): void
{
    new Blueprint($connection, $table);
}
