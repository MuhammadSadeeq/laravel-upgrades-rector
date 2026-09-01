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
    $table->geometry('points', 'multipoint');
    $table->geometry('lines', 'multilinestring');
    $table->geometry('polygons', 'multipolygon');
    $table->multiPolygonZ('polygons_z');
    $table->timestamps();
});

?>
