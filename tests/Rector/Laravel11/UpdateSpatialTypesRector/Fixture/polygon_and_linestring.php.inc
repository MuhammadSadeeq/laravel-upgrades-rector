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
    $table->geometry('boundary', 'polygon');
    $table->geometry('path', 'linestring');
    $table->timestamps();
});

?>
