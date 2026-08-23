<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('locations', function (Blueprint $table) {
    $table->point('coordinates')->nullable();
});

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('locations', function (Blueprint $table) {
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('coordinates', 'point')->nullable();
});

?>
