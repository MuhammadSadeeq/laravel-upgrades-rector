<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addLegacyNamedPrecision(Blueprint $table): void
{
    $table->float('total', total: 8);
    $table->float('places', places: 2);
    $table->float(column: 'mixed', total: 8, places: 2);
}
