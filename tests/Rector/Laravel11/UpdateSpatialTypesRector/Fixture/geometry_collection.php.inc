<?php

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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('collections', function (Blueprint $table) {
    $table->id();
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('data', subtype: 'geometryCollection');
    $table->timestamps();
});

?>
