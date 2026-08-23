<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('products', function (Blueprint $table) {
    $table->string('sku')->unique();
    $table->decimal('weight', 5, 2)->nullable();
    $table->string('title', 200)->nullable()->change();
    $table->text('description')->change();
    $table->dropColumn(['old_field']);
    $table->integer('stock')->unsigned()->default(0)->change();
});

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('products', function (Blueprint $table) {
    $table->string('sku')->unique();
    $table->decimal('weight', 5, 2)->nullable();
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->string('title', 200)->nullable()->change();
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->text('description')->change();
    $table->dropColumn(['old_field']);
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->integer('stock')->unsigned()->default(0)->change();
});

?>
