<?php

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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('locations', function (Blueprint $table) {
    $table->id();
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('coordinates', subtype: 'point');
    $table->timestamps();
});

?>
