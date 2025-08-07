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
        // Update the status enum to include 'paused'
        DB::statement("ALTER TABLE `matches` MODIFY COLUMN `status` ENUM('upcoming','live','completed','cancelled','pending','scheduled','paused') NOT NULL DEFAULT 'upcoming'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'paused' from the enum
        DB::statement("ALTER TABLE `matches` MODIFY COLUMN `status` ENUM('upcoming','live','completed','cancelled','pending','scheduled') NOT NULL DEFAULT 'upcoming'");
    }
};
