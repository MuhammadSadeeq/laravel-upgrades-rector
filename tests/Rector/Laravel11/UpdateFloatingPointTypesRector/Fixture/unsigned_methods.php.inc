<?php

namespace App\Fixtures\Laravel11\UpdateFloatingPointTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('payments', function (Blueprint $table) {
    $table->unsignedDecimal('amount', 8, 2);
    $table->unsignedDouble('price');
    $table->float('rate', 5, 2)->unsigned();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateFloatingPointTypesRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('payments', function (Blueprint $table) {
    $table->decimal('amount', 8, 2)->unsigned();
    $table->double('price')->unsigned();
    $table->float('rate', 5, 2)->unsigned();
});

?>
