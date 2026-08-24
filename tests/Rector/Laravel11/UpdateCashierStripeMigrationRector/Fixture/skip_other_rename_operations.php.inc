<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeMigrationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OtherMigrationChanges extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('old_name', 'new_name');
            $table->renameColumn('status', 'state');
        });
    }
}

?>
