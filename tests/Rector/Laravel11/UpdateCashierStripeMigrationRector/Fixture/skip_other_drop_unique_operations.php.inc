<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeMigrationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OtherDropUniqueOperations extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku', 'vendor_id']);
            $table->dropUnique(['email']);
        });
    }
}

?>
