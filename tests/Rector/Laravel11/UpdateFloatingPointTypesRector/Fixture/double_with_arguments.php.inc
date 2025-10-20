<?php

use Illuminate\Database\Schema\Blueprint;

class CreateProductsTable
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->double('price', 8, 2);
            $table->double('weight', 10, 3);
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateProductsTable
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->double('price');
            $table->double('weight');
        });
    }
}

?>