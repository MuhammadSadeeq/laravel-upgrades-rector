<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('collections', function (Blueprint $table) {
    $table->id();
    $table->geometryCollection('data');
    $table->timestamps();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('collections', function (Blueprint $table) {
    $table->id();
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('data', 'geometrycollection');
    $table->timestamps();
});

?>
