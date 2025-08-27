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
        // Check current schema for bracket_matches best_of column
        if (Schema::hasTable('bracket_matches')) {
            echo "Fixing bracket_matches best_of enum to handle integer and string values...\n";
            
            // Change to allow both string and integer values (Laravel will handle the conversion)
            // The model already handles this with enum('1', '3', '5', '7') so let's ensure the DB matches
            DB::statement("ALTER TABLE bracket_matches MODIFY COLUMN best_of ENUM('1', '3', '5', '7') DEFAULT '3'");
            
            echo "Updated bracket_matches best_of enum successfully\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bracket_matches')) {
            // Keep the same enum structure
            DB::statement("ALTER TABLE bracket_matches MODIFY COLUMN best_of ENUM('1', '3', '5', '7') DEFAULT '3'");
        }
    }
};