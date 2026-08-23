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
    // @laravel-upgrade spatial-types: review whether geography() with an SRID fits this column better.
    $table->geometry('geo', 'point')->change();
});

?>
