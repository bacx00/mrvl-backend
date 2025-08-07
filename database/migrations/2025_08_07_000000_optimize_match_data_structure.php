<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Optimize match data structure for proper score tracking
     */
    public function up()
    {
        // First, let's clean up the matches table structure
        Schema::table('matches', function (Blueprint $table) {
            // Remove duplicate and conflicting columns
            if (Schema::hasColumn('matches', 'match_format')) {
                $table->dropColumn('match_format');
            }
            
            // Standardize score fields - keep only essential ones
            if (!Schema::hasColumn('matches', 'maps_won_team1')) {
                $table->integer('maps_won_team1')->default(0)->after('team2_score');
            }
            if (!Schema::hasColumn('matches', 'maps_won_team2')) {
                $table->integer('maps_won_team2')->default(0)->after('maps_won_team1');
            }
            
            // Standardize timer fields
            if (Schema::hasColumn('matches', 'current_timer')) {
                $table->dropColumn('current_timer');
            }
            if (Schema::hasColumn('matches', 'live_timer')) {
                $table->dropColumn('live_timer');
            }
            
            // Add proper match state tracking
            if (!Schema::hasColumn('matches', 'current_map_status')) {
                $table->enum('current_map_status', ['upcoming', 'live', 'completed', 'paused'])->default('upcoming');
            }
            
            // Add performance indexes
            $table->index(['status', 'scheduled_at'], 'idx_matches_status_scheduled');
            $table->index(['event_id', 'status'], 'idx_matches_event_status');
            $table->index(['team1_id', 'team2_id'], 'idx_matches_teams');
            $table->index(['maps_won_team1', 'maps_won_team2'], 'idx_matches_score');
        });
        
        // Fix match_maps table if it doesn't exist but match_rounds does
        if (!Schema::hasTable('match_maps') && Schema::hasTable('match_rounds')) {
            Schema::rename('match_rounds', 'match_maps');
        }
        
        // Ensure match_maps table has proper structure
        if (Schema::hasTable('match_maps')) {
            Schema::table('match_maps', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('match_maps', 'capture_progress')) {
                    $table->json('capture_progress')->nullable()->after('checkpoints_reached');
                }
                if (!Schema::hasColumn('match_maps', 'payload_distance')) {
                    $table->decimal('payload_distance', 5, 2)->nullable()->after('capture_progress');
                }
                if (!Schema::hasColumn('match_maps', 'team1_composition')) {
                    $table->json('team1_composition')->nullable()->after('payload_distance');
                }
                if (!Schema::hasColumn('match_maps', 'team2_composition')) {
                    $table->json('team2_composition')->nullable()->after('team1_composition');
                }
                if (!Schema::hasColumn('match_maps', 'hero_swaps')) {
                    $table->json('hero_swaps')->nullable()->after('team2_composition');
                }
                if (!Schema::hasColumn('match_maps', 'live_events')) {
                    $table->json('live_events')->nullable()->after('hero_swaps');
                }
                if (!Schema::hasColumn('match_maps', 'current_round')) {
                    $table->integer('current_round')->default(1)->after('live_events');
                }
                if (!Schema::hasColumn('match_maps', 'current_phase')) {
                    $table->string('current_phase')->nullable()->after('current_round');
                }
                
                // Add performance indexes
                $table->index(['match_id', 'map_number'], 'idx_match_maps_match_num');
                $table->index(['match_id', 'status'], 'idx_match_maps_match_status');
                $table->index(['status', 'started_at'], 'idx_match_maps_status_time');
            });
        } else {
            // Create match_maps table if it doesn't exist
            Schema::create('match_maps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
                $table->integer('map_number');
                $table->string('map_name');
                $table->string('game_mode')->default('Domination');
                $table->enum('status', ['upcoming', 'live', 'completed', 'paused'])->default('upcoming');
                $table->integer('team1_score')->default(0);
                $table->integer('team2_score')->default(0);
                $table->integer('team1_rounds')->default(0);
                $table->integer('team2_rounds')->default(0);
                $table->foreignId('winner_id')->nullable()->constrained('teams');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->boolean('overtime')->default(false);
                $table->integer('overtime_duration')->nullable();
                $table->json('checkpoints_reached')->nullable();
                $table->json('capture_progress')->nullable();
                $table->decimal('payload_distance', 5, 2)->nullable();
                $table->json('team1_composition')->nullable();
                $table->json('team2_composition')->nullable();
                $table->json('hero_swaps')->nullable();
                $table->json('live_events')->nullable();
                $table->integer('current_round')->default(1);
                $table->string('current_phase')->nullable();
                $table->timestamps();
                
                // Add indexes for performance
                $table->unique(['match_id', 'map_number']);
                $table->index(['match_id', 'status'], 'idx_match_maps_match_status');
                $table->index(['status', 'started_at'], 'idx_match_maps_status_time');
            });
        }
        
        // Create match_player_stats if it doesn't exist
        if (!Schema::hasTable('match_player_stats')) {
            Schema::create('match_player_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
                $table->foreignId('map_id')->nullable()->constrained('match_maps')->onDelete('cascade');
                $table->foreignId('player_id')->constrained('players');
                $table->foreignId('team_id')->constrained('teams');
                
                // Hero Information
                $table->string('hero');
                $table->enum('hero_role', ['Vanguard', 'Duelist', 'Strategist']);
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
                
                // Performance metrics
                $table->decimal('performance_rating', 4, 2)->nullable();
                $table->boolean('player_of_the_match')->default(false);
                
                $table->timestamps();
                
                // Add indexes
                $table->index(['match_id', 'player_id'], 'idx_match_stats_match_player');
                $table->index(['map_id', 'player_id'], 'idx_match_stats_map_player');
                $table->index(['team_id', 'match_id'], 'idx_match_stats_team_match');
            });
        }
        
        // Migrate data from series_score fields to new maps_won fields and sync scores
        DB::statement('
            UPDATE matches 
            SET maps_won_team1 = COALESCE(series_score_team1, team1_score, 0),
                maps_won_team2 = COALESCE(series_score_team2, team2_score, 0)
            WHERE maps_won_team1 = 0 AND maps_won_team2 = 0
        ');
        
        // Update team scores to reflect series scores
        DB::statement('
            UPDATE matches 
            SET team1_score = maps_won_team1,
                team2_score = maps_won_team2
            WHERE maps_won_team1 > 0 OR maps_won_team2 > 0
        ');
        
        // Clean up orphaned data
        if (Schema::hasTable('match_maps')) {
            DB::statement('DELETE FROM match_maps WHERE match_id NOT IN (SELECT id FROM matches)');
        }
    }
    
    /**
     * Reverse the migrations
     */
    public function down()
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('idx_matches_status_scheduled');
            $table->dropIndex('idx_matches_event_status');
            $table->dropIndex('idx_matches_teams');
            $table->dropIndex('idx_matches_score');
            
            if (Schema::hasColumn('matches', 'maps_won_team1')) {
                $table->dropColumn('maps_won_team1');
            }
            if (Schema::hasColumn('matches', 'maps_won_team2')) {
                $table->dropColumn('maps_won_team2');
            }
            if (Schema::hasColumn('matches', 'current_map_status')) {
                $table->dropColumn('current_map_status');
            }
        });
        
        if (Schema::hasTable('match_maps')) {
            Schema::table('match_maps', function (Blueprint $table) {
                $table->dropIndex('idx_match_maps_match_num');
                $table->dropIndex('idx_match_maps_match_status');
                $table->dropIndex('idx_match_maps_status_time');
            });
        }
    }
};