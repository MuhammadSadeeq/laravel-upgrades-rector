<?php

use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // These should not be changed
            $table->double('score');
            $table->float('rating');
            $table->decimal('balance', 10, 2);
            $table->string('name');
            $table->integer('age');
        });
    }
}

?>