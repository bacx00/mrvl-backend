<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Increase earnings column size to handle larger values
            $table->decimal('earnings', 15, 2)->default(0)->change();
        });
        
        Schema::table('players', function (Blueprint $table) {
            // Increase earnings column size to handle larger values
            $table->decimal('earnings', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Revert to original size
            $table->decimal('earnings', 12, 2)->default(0)->change();
        });
        
        Schema::table('players', function (Blueprint $table) {
            // Revert to original size
            $table->decimal('earnings', 12, 2)->nullable()->change();
        });
    }
};
