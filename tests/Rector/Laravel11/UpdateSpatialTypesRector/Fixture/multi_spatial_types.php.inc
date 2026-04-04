<?php

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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('geometry', function (Blueprint $table) {
    $table->id();
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('points', subtype: 'multiPoint');
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('lines', subtype: 'multiLineString');
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('polygons', subtype: 'multiPolygon');
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('polygons_z', subtype: 'multiPolygonZ');
    $table->timestamps();
});

?>
