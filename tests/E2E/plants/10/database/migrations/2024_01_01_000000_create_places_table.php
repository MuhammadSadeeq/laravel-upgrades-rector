<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('external_id');
            $table->double('amount', 8, 2);
            $table->float('rate');
            $table->float('score', 5, 2);
            $table->point('location');
            $table->point('geo', 4326);
            $table->geometry('area');
            $table->string('slug');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
