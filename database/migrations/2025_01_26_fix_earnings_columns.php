<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix teams earnings column
        Schema::table('teams', function (Blueprint $table) {
            // First, clean up any non-numeric values
            DB::statement("UPDATE teams SET earnings = REPLACE(REPLACE(earnings, '$', ''), ',', '') WHERE earnings IS NOT NULL");
            DB::statement("UPDATE teams SET earnings = NULL WHERE earnings = '' OR earnings = '0'");
            
            // Change column type to decimal
            $table->decimal('earnings', 12, 2)->nullable()->change();
        });
        
        // Fix players earnings column
        Schema::table('players', function (Blueprint $table) {
            // First, clean up any non-numeric values
            DB::statement("UPDATE players SET earnings = REPLACE(REPLACE(earnings, '$', ''), ',', '') WHERE earnings IS NOT NULL");
            DB::statement("UPDATE players SET earnings = NULL WHERE earnings = '' OR earnings = '0'");
            
            // Change column type to decimal
            $table->decimal('earnings', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('earnings')->nullable()->change();
        });
        
        Schema::table('players', function (Blueprint $table) {
            $table->string('earnings')->nullable()->change();
        });
    }
};