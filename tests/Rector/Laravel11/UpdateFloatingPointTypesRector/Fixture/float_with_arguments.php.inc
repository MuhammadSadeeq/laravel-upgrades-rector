<?php

use Illuminate\Database\Schema\Blueprint;

class CreateOrdersTable
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->float('amount', 8, 2);
            $table->float('discount', 5, 2);
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateOrdersTable
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->float('amount');
            $table->float('discount');
        });
    }
}

?>