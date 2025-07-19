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
        Schema::table('matches', function (Blueprint $table) {
            // Add bracket_position as integer if it doesn't exist
            if (!Schema::hasColumn('matches', 'bracket_position') || gettype(DB::select("SHOW COLUMNS FROM matches WHERE Field = 'bracket_position'")[0]->Type ?? '') === 'string') {
                // First drop the old column if it's a string
                if (Schema::hasColumn('matches', 'bracket_position')) {
                    $table->dropColumn('bracket_position');
                }
            }
        });
        
        Schema::table('matches', function (Blueprint $table) {
            // Add bracket_position as integer
            if (!Schema::hasColumn('matches', 'bracket_position')) {
                $table->integer('bracket_position')->nullable()->after('round');
            }
            
            // Add bracket_type if it doesn't exist
            if (!Schema::hasColumn('matches', 'bracket_type')) {
                $table->string('bracket_type')->nullable()->default('main')->after('round');
            }
        });
        
        // Add index for efficient bracket queries
        Schema::table('matches', function (Blueprint $table) {
            // Check if index exists before adding
            $indexExists = DB::select("SHOW INDEX FROM matches WHERE Key_name = 'bracket_lookup_idx'");
            if (empty($indexExists)) {
                $table->index(['event_id', 'bracket_type', 'round', 'bracket_position'], 'bracket_lookup_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Drop index if exists
            $indexExists = DB::select("SHOW INDEX FROM matches WHERE Key_name = 'bracket_lookup_idx'");
            if (!empty($indexExists)) {
                $table->dropIndex('bracket_lookup_idx');
            }
            
            // Keep the columns for compatibility
        });
    }
};