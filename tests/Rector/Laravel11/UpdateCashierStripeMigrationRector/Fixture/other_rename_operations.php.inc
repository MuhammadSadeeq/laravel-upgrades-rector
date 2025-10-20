<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OtherMigrationChanges extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // These should not be changed
            $table->renameColumn('old_name', 'new_name');
            $table->renameColumn('status', 'state');
            $table->renameColumn('type', 'category');
        });
    }
}

?>