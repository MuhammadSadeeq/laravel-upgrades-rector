<?php

namespace App\Fixtures\Laravel11\UpdateFloatingPointTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('mixed', function (Blueprint $table) {
    $table->double('price', 8, 2);
    $table->float('discount', 5, 2);
    $table->unsignedDouble('amount');
    $table->double('rating');
    $table->string('name');
    $table->decimal('balance', 10, 2);
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateFloatingPointTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('mixed', function (Blueprint $table) {
    $table->double('price', 8, 2);
    $table->float('discount', 5, 2);
    $table->double('amount')->unsigned();
    $table->double('rating');
    $table->string('name');
    $table->decimal('balance', 10, 2);
});

?>
