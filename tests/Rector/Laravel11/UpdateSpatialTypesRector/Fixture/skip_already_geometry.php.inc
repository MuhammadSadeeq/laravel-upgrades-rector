<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('locations', function (Blueprint $table) {
    $table->geometry('coordinates', subtype: 'point');
});

?>
