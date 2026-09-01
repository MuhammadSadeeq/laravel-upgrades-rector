<?php

namespace App\Fixtures\Laravel11\UpdateFloatingPointTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('products', function (Blueprint $table) {
    $table->double('price', 8, 2);
    $table->double('weight', 10, 3);
});
