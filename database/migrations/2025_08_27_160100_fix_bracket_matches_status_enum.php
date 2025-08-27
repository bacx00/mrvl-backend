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
        // Check current schema for bracket_matches status column
        if (Schema::hasTable('bracket_matches')) {
            echo "Fixing bracket_matches status enum to include all valid statuses...\n";
            
            // Update the enum to include all statuses from the model
            DB::statement("ALTER TABLE bracket_matches MODIFY COLUMN status ENUM('pending', 'ready', 'live', 'completed', 'forfeit', 'cancelled') DEFAULT 'pending'");
            
            echo "Updated bracket_matches status enum successfully\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bracket_matches')) {
            // Revert to original enum (if needed)
            DB::statement("ALTER TABLE bracket_matches MODIFY COLUMN status ENUM('pending', 'live', 'completed', 'forfeit', 'cancelled') DEFAULT 'pending'");
        }
    }
};