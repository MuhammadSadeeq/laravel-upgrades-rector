<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OtherDropUniqueOperations extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // These should not be changed
            $table->dropUnique(['sku', 'vendor_id']);
            $table->dropUnique(['email']);
            $table->dropUnique(['category_id', 'slug']);
        });
    }
}

?>