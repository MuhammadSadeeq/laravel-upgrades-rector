<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addPreciseDouble(Blueprint $table): void
{
    $table->double('ratio', 10, 4);
}
