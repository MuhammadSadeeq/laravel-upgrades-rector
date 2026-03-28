<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('orders', function (Blueprint $table) {
    $table->float('amount', 8, 2);
    $table->float('discount', 5, 2);
});

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('orders', function (Blueprint $table) {
    $table->float('amount');
    $table->float('discount');
});

?>
