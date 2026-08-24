<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('geometry', function (Blueprint $table) {
    $table->id();
    $table->multiPoint('points');
    $table->multiLineString('lines');
    $table->multiPolygon('polygons');
    $table->multiPolygonZ('polygons_z');
    $table->timestamps();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('geometry', function (Blueprint $table) {
    $table->id();
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('points', 'multipoint');
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('lines', 'multilinestring');
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('polygons', 'multipolygon');
    $table->multiPolygonZ('polygons_z');
    $table->timestamps();
});

?>
