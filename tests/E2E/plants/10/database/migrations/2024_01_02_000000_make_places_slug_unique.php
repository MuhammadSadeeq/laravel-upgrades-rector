<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            // Laravel 11 no longer preserves index modifiers during change().
            $table->string('slug')->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
        });
    }
};
