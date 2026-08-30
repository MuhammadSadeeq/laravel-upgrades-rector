<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addUuidColumn(Blueprint $table): void
{
    $table->uuid('external_id');
}
