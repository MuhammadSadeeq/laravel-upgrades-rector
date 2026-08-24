<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('locations', function (Blueprint $table) {
    $table->id();
    $table->point('coordinates');
    $table->timestamps();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('locations', function (Blueprint $table) {
    $table->id();
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('coordinates', 'point');
    $table->timestamps();
});

?>
