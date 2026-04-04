<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', function (Blueprint $table) {
    $table->uuid('uuid');
});

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', function (Blueprint $table) {
    // Laravel 11: the new mariadb driver creates native UUID columns for uuid() Use char(..., 36) instead if you switch to the mariadb driver and need the previous behavior
    $table->uuid('uuid');
});

?>
