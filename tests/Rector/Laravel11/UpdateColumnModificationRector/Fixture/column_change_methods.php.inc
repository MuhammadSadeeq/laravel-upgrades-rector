<?php

namespace App\Fixtures\Laravel11\UpdateColumnModificationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->integer('votes')->nullable()->change();
    $table->string('name', 100)->change();
    $table->decimal('price', 8, 2)->unsigned()->default(0)->change();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateColumnModificationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->integer('votes')->nullable()->change();
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->string('name', 100)->change();
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->decimal('price', 8, 2)->unsigned()->default(0)->change();
});

?>
