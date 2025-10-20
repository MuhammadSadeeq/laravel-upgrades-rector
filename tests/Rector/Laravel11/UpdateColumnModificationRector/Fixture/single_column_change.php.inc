<?php

use Illuminate\Database\Schema\Blueprint;

class ProductsTableUpdate
{
    public function modifyColumns(Blueprint $table)
    {
        $table->text('description')->nullable()->change();
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class ProductsTableUpdate
{
    public function modifyColumns(Blueprint $table)
    {
        $table->text('description')->nullable()->change();
    }
}

?>