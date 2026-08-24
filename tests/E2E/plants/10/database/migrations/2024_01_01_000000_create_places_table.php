<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->primary();
            $table->double('rating', 8, 2);
            $table->float('score', 5, 2);
            $table->point('geo', 4326);
            $table->string('slug')->unique()->change();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
