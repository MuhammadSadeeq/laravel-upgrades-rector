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
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->point('geo')->change();
});

?>
