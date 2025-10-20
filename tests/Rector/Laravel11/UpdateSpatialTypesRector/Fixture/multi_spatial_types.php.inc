<?php

use Illuminate\Database\Schema\Blueprint;

class CreateGeometryTable
{
    public function up()
    {
        Schema::create('geometry', function (Blueprint $table) {
            $table->id();
            $table->multiPoint('points');
            $table->multiLineString('lines');
            $table->multiPolygon('polygons');
            $table->multiPolygonZ('polygons_z');
            $table->timestamps();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateGeometryTable
{
    public function up()
    {
        Schema::create('geometry', function (Blueprint $table) {
            $table->id();
            $table->geometry('points');
            $table->geometry('lines');
            $table->geometry('polygons');
            $table->geometry('polygons_z');
            $table->timestamps();
        });
    }
}

?>