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
    $table->geometry('data', 'geometrycollection');
    $table->timestamps();
});

?>
