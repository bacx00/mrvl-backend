<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add platform support and update regions for Marvel Rivals official tournament structure
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Add platform field for Marvel Rivals (PC/Console)
            $table->enum('platform', ['PC', 'Console'])->default('PC')->after('region');
            
            // Add game field to specify this is Marvel Rivals
            $table->string('game')->default('Marvel Rivals')->after('platform');
            
            // Add division field for Marvel Rivals ranking system
            $table->string('division')->nullable()->after('game');
            
            // Add recent form array for last 5 matches (W/L)
            $table->json('recent_form')->nullable()->after('division');
            
            // Add player count for team roster size
            $table->integer('player_count')->default(0)->after('recent_form');
            
            // Add index for platform filtering
            $table->index(['region', 'platform', 'rating']);
        });
        
        // Update existing teams to have proper Marvel Rivals regions
        // Map old regions to new Marvel Rivals official tournament regions
        DB::table('teams')->where('region', 'NA')->update(['region' => 'Americas']);
        DB::table('teams')->where('region', 'US')->update(['region' => 'Americas']);
        DB::table('teams')->where('region', 'EU')->update(['region' => 'EMEA']);
        DB::table('teams')->where('region', 'APAC')->update(['region' => 'Asia']);
        DB::table('teams')->where('region', 'KR')->update(['region' => 'Asia']);
        DB::table('teams')->where('region', 'JP')->update(['region' => 'Asia']);
        DB::table('teams')->where('region', 'AU')->update(['region' => 'Oceania']);
        DB::table('teams')->where('region', 'CN')->update(['region' => 'China']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['platform', 'game', 'division', 'recent_form', 'player_count']);
            $table->dropIndex(['region', 'platform', 'rating']);
        });
    }
};
