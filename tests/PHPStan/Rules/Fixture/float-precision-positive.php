<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addPreciseFloat(Blueprint $table): void
{
    $table->float('ratio', 8, 2);
}
