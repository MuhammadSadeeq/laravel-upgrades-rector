<?php

namespace App\Fixtures\Laravel11\UpdateColumnModificationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('products', function (Blueprint $table) {
    $table->text('description')->nullable()->change();
});

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateColumnModificationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('products', function (Blueprint $table) {
    // @laravel-upgrade column-change change() now requires all column modifiers to be explicitly re-specified. Review this migration.
    $table->text('description')->nullable()->change();
});

?>
