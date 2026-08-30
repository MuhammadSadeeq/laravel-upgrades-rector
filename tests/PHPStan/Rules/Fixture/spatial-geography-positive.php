<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addGeographyColumn(Blueprint $table): void
{
    $table->geography('location');
}
