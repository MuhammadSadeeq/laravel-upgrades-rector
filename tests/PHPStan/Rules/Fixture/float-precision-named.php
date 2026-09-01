<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addNamedPrecision(Blueprint $table): void
{
    $table->float('positional', 24);
    $table->float('ratio', precision: 24);
    $table->float(column: 'score', precision: 53);
}
