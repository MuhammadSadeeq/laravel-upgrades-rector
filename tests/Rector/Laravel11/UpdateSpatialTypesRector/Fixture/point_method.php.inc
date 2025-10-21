<?php

use Illuminate\Database\Schema\Blueprint;

class CreateLocationsTable
{
    public function up()
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->point('coordinates');
            $table->timestamps();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateLocationsTable
{
    public function up()
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->geometry('coordinates', subtype: 'point');
            $table->timestamps();
        });
    }
}

?>