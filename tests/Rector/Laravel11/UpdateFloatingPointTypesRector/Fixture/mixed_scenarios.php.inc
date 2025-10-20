<?php

use Illuminate\Database\Schema\Blueprint;

class CreateMixedTable
{
    public function up()
    {
        Schema::table('mixed', function (Blueprint $table) {
            // Should be updated - remove arguments
            $table->double('price', 8, 2);
            $table->float('discount', 5, 2);

            // Should be converted from unsigned
            $table->unsignedDouble('amount');

            // Should not be changed
            $table->double('rating');
            $table->string('name');
            $table->decimal('balance', 10, 2);
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateMixedTable
{
    public function up()
    {
        Schema::table('mixed', function (Blueprint $table) {
            // Should be updated - remove arguments
            $table->double('price');
            $table->float('discount');

            // Should be converted from unsigned
            $table->double('amount');

            // Should not be changed
            $table->double('rating');
            $table->string('name');
            $table->decimal('balance', 10, 2);
        });
    }
}

?>