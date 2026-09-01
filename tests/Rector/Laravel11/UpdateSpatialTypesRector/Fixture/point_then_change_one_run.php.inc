<?php

namespace Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('places', function (Blueprint $table) {
    $table->point('geo')->change();
});

?>
-----
<?php

namespace Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('places', function (Blueprint $table) {
    $table->geometry('geo', 'point')->change();
});

?>
