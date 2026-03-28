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
    $table->geometry('boundary', subtype: 'polygon');
    $table->geometry('path', subtype: 'lineString');
    $table->timestamps();
});

?>
