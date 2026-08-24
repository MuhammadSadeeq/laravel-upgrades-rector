<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('areas', function (Blueprint $table) {
    $table->id();
    $table->polygon('boundary');
    $table->lineString('path');
    $table->timestamps();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('areas', function (Blueprint $table) {
    $table->id();
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('boundary', 'polygon');
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('path', 'linestring');
    $table->timestamps();
});

?>
