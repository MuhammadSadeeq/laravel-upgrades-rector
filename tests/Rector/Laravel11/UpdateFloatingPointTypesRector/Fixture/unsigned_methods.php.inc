<?php

use Illuminate\Database\Schema\Blueprint;

class CreatePaymentsTable
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedDecimal('amount', 8, 2);
            $table->unsignedDouble('price');
            $table->unsignedFloat('rate', 5, 2);
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreatePaymentsTable
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 8, 2);
            $table->double('price');
            $table->float('rate', 5, 2);
        });
    }
}

?>