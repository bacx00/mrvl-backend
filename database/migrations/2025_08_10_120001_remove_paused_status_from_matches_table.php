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
        // First, convert any 'paused' matches to 'live' status
        DB::statement("UPDATE `matches` SET `status` = 'live' WHERE `status` = 'paused'");
        
        // Remove 'paused' from the status enum
        DB::statement("ALTER TABLE `matches` MODIFY COLUMN `status` ENUM('upcoming','live','completed','cancelled','pending','scheduled') NOT NULL DEFAULT 'upcoming'");
        
        // Also update the current_map_status enum if it exists
        if (Schema::hasColumn('matches', 'current_map_status')) {
            DB::statement("UPDATE `matches` SET `current_map_status` = 'live' WHERE `current_map_status` = 'paused'");
            DB::statement("ALTER TABLE `matches` MODIFY COLUMN `current_map_status` ENUM('upcoming','live','completed') DEFAULT 'upcoming'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add 'paused' back to the enum
        DB::statement("ALTER TABLE `matches` MODIFY COLUMN `status` ENUM('upcoming','live','completed','cancelled','pending','scheduled','paused') NOT NULL DEFAULT 'upcoming'");
        
        // Add 'paused' back to current_map_status if column exists
        if (Schema::hasColumn('matches', 'current_map_status')) {
            DB::statement("ALTER TABLE `matches` MODIFY COLUMN `current_map_status` ENUM('upcoming','live','completed','paused') DEFAULT 'upcoming'");
        }
    }
};