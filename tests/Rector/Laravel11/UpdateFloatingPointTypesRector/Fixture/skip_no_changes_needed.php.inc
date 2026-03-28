<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', function (Blueprint $table) {
    $table->double('score');
    $table->float('rating');
    $table->decimal('balance', 10, 2);
    $table->string('name');
    $table->integer('age');
});

?>
