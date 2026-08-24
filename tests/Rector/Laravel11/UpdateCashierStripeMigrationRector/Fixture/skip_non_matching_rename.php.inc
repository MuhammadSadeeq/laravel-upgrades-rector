<?php

namespace App\Fixtures\Laravel11\UpdateCashierStripeMigrationRector;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('first_name', 'full_name');
        });
    }
}

?>
