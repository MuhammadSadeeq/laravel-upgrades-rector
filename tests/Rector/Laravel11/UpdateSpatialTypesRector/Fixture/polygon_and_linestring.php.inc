<?php

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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('areas', function (Blueprint $table) {
    $table->id();
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('boundary', subtype: 'polygon');
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $table->geometry('path', subtype: 'lineString');
    $table->timestamps();
});

?>
