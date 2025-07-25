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
            // Add missing columns
            if (!Schema::hasColumn('matches', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('started_at');
            }
            
            // Add paused status to enum
            \DB::statement("ALTER TABLE matches MODIFY COLUMN status ENUM('upcoming', 'live', 'completed', 'paused') DEFAULT 'upcoming'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            if (Schema::hasColumn('matches', 'ended_at')) {
                $table->dropColumn('ended_at');
            }
        });
        
        // Revert status enum
        \DB::statement("ALTER TABLE matches MODIFY COLUMN status ENUM('upcoming', 'live', 'completed') DEFAULT 'upcoming'");
    }
};