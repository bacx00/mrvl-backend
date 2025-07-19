<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComprehensiveMatchSystem extends Migration
{
    public function up()
    {
        // Drop existing matches table if it exists
        Schema::dropIfExists('match_player_stats');
        Schema::dropIfExists('match_maps');
        Schema::dropIfExists('matches');
        
        // Create comprehensive matches table
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            
            // Team References
            $table->foreignId('team1_id')->constrained('teams');
            $table->foreignId('team2_id')->constrained('teams');
            $table->foreignId('event_id')->nullable()->constrained('events');
            
            // Match Information
            $table->string('format', 10)->default('BO3'); // BO1, BO3, BO5, BO7, BO9
            $table->string('status', 20)->default('upcoming');
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            
            // Scores
            $table->integer('team1_score')->default(0);
            $table->integer('team2_score')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('teams');
            
            // Tournament Information
            $table->string('round')->nullable(); // Quarterfinals, Semifinals, etc
            $table->string('bracket_position')->nullable(); // Upper/Lower bracket
            $table->string('match_number')->nullable(); // Match identifier in bracket
            
            // Streaming & VODs
            $table->json('stream_urls')->nullable(); // Multiple stream links
            $table->json('vod_urls')->nullable(); // VOD links after match
            $table->json('betting_urls')->nullable(); // Betting site links
            $table->integer('viewers')->default(0);
            $table->integer('peak_viewers')->default(0);
            
            // Match Settings
            $table->boolean('hero_bans_enabled')->default(false);
            $table->json('banned_heroes')->nullable(); // Array of banned heroes
            $table->boolean('is_remake')->default(false);
            $table->foreignId('remake_of')->nullable()->constrained('matches');
            
            // Additional Data
            $table->json('map_pool')->nullable(); // Available maps for this match
            $table->json('notes')->nullable(); // Admin notes
            $table->boolean('featured')->default(false);
            $table->json('sponsors')->nullable();
            
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index(['event_id', 'status']);
            $table->index(['team1_id', 'team2_id']);
        });
        
        // Create match_maps table for individual map results
        Schema::create('match_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            
            // Map Information
            $table->integer('map_number');
            $table->string('map_name');
            $table->string('game_mode'); // Domination, Convoy, Convergence
            $table->string('status', 20)->default('upcoming');
            
            // Scores
            $table->integer('team1_score')->default(0);
            $table->integer('team2_score')->default(0);
            $table->integer('team1_rounds')->default(0); // For Domination
            $table->integer('team2_rounds')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('teams');
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('overtime')->default(false);
            $table->integer('overtime_duration')->nullable();
            
            // Map-specific data
            $table->json('checkpoints_reached')->nullable(); // For Convoy
            $table->json('capture_progress')->nullable(); // For Convergence/Domination
            $table->decimal('payload_distance', 5, 2)->nullable(); // For Convoy
            
            // Team Compositions
            $table->json('team1_composition')->nullable(); // Hero picks with players
            $table->json('team2_composition')->nullable();
            $table->json('hero_swaps')->nullable(); // Mid-game hero changes
            
            // Live Data
            $table->json('live_events')->nullable(); // Kill feed, objectives, etc
            $table->integer('current_round')->default(1);
            $table->string('current_phase')->nullable(); // attack/defend, capture/escort
            
            $table->timestamps();
            $table->unique(['match_id', 'map_number']);
            $table->index(['match_id', 'status']);
        });
        
        // Create match_player_stats table for individual player performance
        Schema::create('match_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('map_id')->nullable()->constrained('match_maps')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players');
            $table->foreignId('team_id')->constrained('teams');
            
            // Hero Information
            $table->string('hero');
            $table->string('hero_role'); // Vanguard, Duelist, Strategist
            $table->integer('time_played_seconds')->default(0);
            
            // Combat Stats
            $table->integer('eliminations')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('deaths')->default(0);
            $table->decimal('kda', 5, 2)->nullable();
            $table->integer('damage_dealt')->default(0);
            $table->integer('damage_taken')->default(0);
            $table->integer('healing_done')->default(0);
            $table->integer('damage_blocked')->default(0);
            
            // Objective Stats
            $table->integer('objective_time')->default(0);
            $table->integer('objective_kills')->default(0);
            $table->decimal('payload_distance', 5, 2)->nullable();
            $table->integer('capture_progress')->default(0);
            
            // Ultimate Stats
            $table->integer('ultimates_earned')->default(0);
            $table->integer('ultimates_used')->default(0);
            $table->integer('ultimate_eliminations')->default(0);
            
            // Accuracy Stats
            $table->integer('shots_fired')->default(0);
            $table->integer('shots_hit')->default(0);
            $table->integer('critical_hits')->default(0);
            $table->decimal('accuracy_percentage', 5, 2)->nullable();
            
            // Advanced Stats
            $table->integer('best_killstreak')->default(0);
            $table->integer('solo_kills')->default(0);
            $table->integer('environmental_kills')->default(0);
            $table->integer('final_blows')->default(0);
            $table->integer('melee_final_blows')->default(0);
            
            // Hero-specific stats (JSON for flexibility)
            $table->json('hero_specific_stats')->nullable();
            
            // Performance Rating
            $table->decimal('performance_rating', 4, 2)->nullable();
            $table->boolean('player_of_the_match')->default(false);
            $table->boolean('player_of_the_map')->default(false);
            
            $table->timestamps();
            $table->index(['match_id', 'player_id']);
            $table->index(['map_id', 'player_id']);
            $table->index(['player_id', 'hero']);
            $table->index(['team_id', 'match_id']);
        });
        
        // Create match_events table for live event tracking
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('map_id')->nullable()->constrained('match_maps')->onDelete('cascade');
            
            $table->string('event_type'); // kill, objective_capture, hero_swap, etc
            $table->integer('game_time_seconds');
            $table->json('event_data'); // Flexible data structure
            $table->foreignId('player_id')->nullable()->constrained('players');
            $table->foreignId('target_player_id')->nullable()->constrained('players');
            
            $table->timestamps();
            $table->index(['match_id', 'event_type']);
            $table->index(['map_id', 'game_time_seconds']);
        });
        
        // Add indexes for performance
        Schema::table('players', function (Blueprint $table) {
            if (!Schema::hasColumn('players', 'total_matches')) {
                $table->integer('total_matches')->default(0);
                $table->integer('total_wins')->default(0);
                $table->decimal('avg_kda', 5, 2)->default(0);
                $table->json('hero_pool')->nullable();
                $table->json('career_stats')->nullable();
            }
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('match_player_stats');
        Schema::dropIfExists('match_maps');
        Schema::dropIfExists('matches');
        
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['total_matches', 'total_wins', 'avg_kda', 'hero_pool', 'career_stats']);
        });
    }
}