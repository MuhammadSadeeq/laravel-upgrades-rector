<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->integer('age');
    $table->geometry('location');
    $table->text('description');
    $table->timestamps();
});

?>
