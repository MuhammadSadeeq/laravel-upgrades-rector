<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function addGeometryColumn(Blueprint $table): void
{
    $table->geometry('location');
}
