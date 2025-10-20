<?php

use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('age');
            $table->geometry('location');
            $table->text('description');
            $table->timestamps();
        });
    }
}

?>