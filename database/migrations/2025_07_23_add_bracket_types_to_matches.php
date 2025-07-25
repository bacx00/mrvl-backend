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
        // First check if column is string type and convert to enum
        $columnType = DB::select("SHOW COLUMNS FROM matches WHERE Field = 'bracket_type'")[0]->Type ?? '';
        
        if (strpos($columnType, 'varchar') !== false || strpos($columnType, 'string') !== false) {
            // Convert string to enum
            DB::statement("ALTER TABLE matches MODIFY COLUMN bracket_type ENUM('main', 'upper', 'lower', 'grand_final', 'third_place', 'round_robin', 'swiss', 'group_a', 'group_b', 'group_c', 'group_d', 'group_e', 'group_f', 'group_g', 'group_h') DEFAULT 'main'");
        } else {
            // Already enum, just update values
            DB::statement("ALTER TABLE matches MODIFY COLUMN bracket_type ENUM('main', 'upper', 'lower', 'grand_final', 'third_place', 'round_robin', 'swiss', 'group_a', 'group_b', 'group_c', 'group_d', 'group_e', 'group_f', 'group_g', 'group_h') DEFAULT 'main'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original bracket types
        DB::statement("ALTER TABLE matches MODIFY COLUMN bracket_type ENUM('main', 'upper', 'lower', 'grand_final') DEFAULT 'main'");
    }
};