<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addStringColumn(Blueprint $table): void
{
    $table->string('external_id');
}
