<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MixedTableOperations extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // New columns - should not be changed
            $table->string('sku')->unique();
            $table->decimal('weight', 5, 2)->nullable();

            // Modified columns - should get comments
            $table->string('title', 200)->nullable()->change();
            $table->text('description')->change();

            // Dropped columns - should not be changed
            $table->dropColumn(['old_field']);

            // Another modified column
            $table->integer('stock')->unsigned()->default(0)->change();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MixedTableOperations extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // New columns - should not be changed
            $table->string('sku')->unique();
            $table->decimal('weight', 5, 2)->nullable();

            // Modified columns - should get comments
            $table->string('title', 200)->nullable()->change();
            $table->text('description')->change();

            // Dropped columns - should not be changed
            $table->dropColumn(['old_field']);

            // Another modified column
            $table->integer('stock')->unsigned()->default(0)->change();
        });
    }
}

?>