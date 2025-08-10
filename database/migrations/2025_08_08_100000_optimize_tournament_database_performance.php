<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration provides comprehensive database optimizations for tournament operations:
     * 1. Performance-optimized indexes for bracket queries
     * 2. Liquipedia-style tournament enhancements
     * 3. Swiss system support
     * 4. Query caching optimization structures
     */
    public function up(): void
    {
        // Add Liquipedia-style enhancements to bracket_matches
        Schema::table('bracket_matches', function (Blueprint $table) {
            // Liquipedia compatibility columns
            if (!Schema::hasColumn('bracket_matches', 'liquipedia_id')) {
                $table->string('liquipedia_id', 20)->nullable()->after('match_id');
                $table->index('liquipedia_id');
            }
            
            if (!Schema::hasColumn('bracket_matches', 'dependency_matches')) {
                $table->json('dependency_matches')->nullable()->after('team2_source')
                    ->comment('JSON array of match IDs this match depends on');
            }
            
            if (!Schema::hasColumn('bracket_matches', 'map_veto_data')) {
                $table->json('map_veto_data')->nullable()->after('dependency_matches')
                    ->comment('Map veto process and picks/bans data');
            }
            
            if (!Schema::hasColumn('bracket_matches', 'next_match_upper')) {
                $table->integer('next_match_upper')->nullable()->after('loser_advances_to')
                    ->comment('Next match ID for winner (upper bracket)');
                $table->index('next_match_upper');
            }
            
            if (!Schema::hasColumn('bracket_matches', 'next_match_lower')) {
                $table->integer('next_match_lower')->nullable()->after('next_match_upper')
                    ->comment('Next match ID for loser (lower bracket)');
                $table->index('next_match_lower');
            }
            
            if (!Schema::hasColumn('bracket_matches', 'bracket_reset')) {
                $table->boolean('bracket_reset')->default(false)->after('notes')
                    ->comment('Grand finals bracket reset flag');
            }
        });

        // Create Swiss system standings table
        if (!Schema::hasTable('bracket_swiss_standings')) {
            Schema::create('bracket_swiss_standings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bracket_id')->constrained('bracket_stages')->onDelete('cascade');
                $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
                $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
                
                // Swiss system scoring
                $table->integer('wins')->default(0);
                $table->integer('losses')->default(0);
                $table->integer('draws')->default(0);
                $table->decimal('match_win_percentage', 5, 2)->default(0.00);
                $table->decimal('game_win_percentage', 5, 2)->default(0.00);
                $table->decimal('opponent_match_win_percentage', 5, 2)->default(0.00);
                
                // Tiebreaker calculations
                $table->decimal('buchholz_score', 10, 2)->default(0.00)
                    ->comment('Sum of opponents match win percentages');
                $table->integer('round_differential')->default(0)
                    ->comment('Total round wins minus losses');
                $table->integer('games_won')->default(0);
                $table->integer('games_lost')->default(0);
                
                // Swiss pairing history
                $table->json('opponent_history')->nullable()
                    ->comment('Array of team IDs already faced');
                $table->integer('current_round')->default(0);
                $table->boolean('eliminated')->default(false);
                $table->boolean('qualified')->default(false);
                
                $table->timestamps();
                
                // Optimized indexes for Swiss calculations
                $table->unique(['bracket_id', 'team_id']);
                $table->index(['bracket_id', 'wins', 'losses']);
                $table->index(['bracket_id', 'buchholz_score']);
                $table->index(['event_id', 'qualified', 'eliminated']);
                $table->index(['current_round', 'wins']);
            });
        }

        // Create tournament phases table for multi-stage tournaments
        if (!Schema::hasTable('tournament_phases')) {
            Schema::create('tournament_phases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
                $table->string('phase_name', 100); // 'Open Qualifier', 'Closed Qualifier', 'Playoffs'
                $table->enum('phase_type', [
                    'open_qualifier', 'closed_qualifier', 'playoffs', 
                    'group_stage', 'swiss_stage', 'bracket_stage', 'finals'
                ]);
                $table->integer('phase_number')->default(1);
                $table->timestamp('start_date');
                $table->timestamp('end_date')->nullable();
                $table->enum('format', [
                    'swiss', 'double_elim', 'single_elim', 'round_robin', 
                    'group_stage', 'bracket_stage'
                ]);
                $table->integer('current_round')->default(0);
                $table->integer('total_rounds')->default(0);
                $table->integer('teams_advance')->default(0)
                    ->comment('Number of teams advancing to next phase');
                $table->json('format_settings')->nullable()
                    ->comment('Phase-specific configuration');
                $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
                
                $table->timestamps();
                
                $table->index(['event_id', 'phase_number', 'status']);
                $table->index(['phase_type', 'status']);
                $table->index(['start_date', 'end_date']);
            });
        }

        // Performance indexes for critical queries
        
        // 1. Bracket retrieval optimizations
        Schema::table('bracket_matches', function (Blueprint $table) {
            // Covering index for bracket display queries
            if (!$this->indexExists('bracket_matches', 'idx_bracket_display_covering')) {
                DB::statement('CREATE INDEX idx_bracket_display_covering ON bracket_matches (bracket_stage_id, round_number, match_number) INCLUDE (team1_id, team2_id, team1_score, team2_score, status, winner_id)');
            }
            
            // Live matches optimization
            if (!$this->indexExists('bracket_matches', 'idx_live_matches')) {
                $table->index(['status', 'started_at', 'scheduled_at'], 'idx_live_matches');
            }
            
            // Tournament progression queries
            if (!$this->indexExists('bracket_matches', 'idx_progression')) {
                $table->index(['tournament_id', 'status', 'completed_at'], 'idx_progression');
            }
        });

        // 2. Team and player lookup optimizations
        Schema::table('teams', function (Blueprint $table) {
            if (!$this->indexExists('teams', 'idx_team_search')) {
                $table->index(['name', 'region', 'status'], 'idx_team_search');
            }
            
            if (!$this->indexExists('teams', 'idx_team_rankings')) {
                $table->index(['region', 'ranking', 'wins', 'losses'], 'idx_team_rankings');
            }
        });

        Schema::table('players', function (Blueprint $table) {
            if (!$this->indexExists('players', 'idx_player_search')) {
                $table->index(['username', 'team_id', 'role'], 'idx_player_search');
            }
            
            if (!$this->indexExists('players', 'idx_player_stats')) {
                $table->index(['team_id', 'elo_rating', 'earnings'], 'idx_player_stats');
            }
        });

        // 3. Match statistics optimizations
        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                if (!$this->indexExists('match_player_stats', 'idx_match_stats_analysis')) {
                    $table->index(['match_id', 'player_id', 'hero_name'], 'idx_match_stats_analysis');
                }
                
                if (!$this->indexExists('match_player_stats', 'idx_player_performance')) {
                    $table->index(['player_id', 'hero_name', 'kills', 'deaths'], 'idx_player_performance');
                }
            });
        }

        // 4. Event and tournament queries
        Schema::table('events', function (Blueprint $table) {
            if (!$this->indexExists('events', 'idx_events_active')) {
                $table->index(['status', 'start_date', 'tier'], 'idx_events_active');
            }
            
            if (!$this->indexExists('events', 'idx_events_featured')) {
                $table->index(['is_featured', 'region', 'start_date'], 'idx_events_featured');
            }
        });

        // 5. GIN indexes for JSONB fields (PostgreSQL-style, adapted for MySQL JSON)
        if (Schema::hasTable('bracket_matches')) {
            // For MySQL 8.0+ functional indexes on JSON
            if ($this->supportsFunctionalIndexes()) {
                DB::statement('CREATE INDEX idx_dependency_matches_gin ON bracket_matches ((CAST(dependency_matches AS JSON)))');
                DB::statement('CREATE INDEX idx_map_veto_data_gin ON bracket_matches ((CAST(map_veto_data AS JSON)))');
            }
        }

        // 6. Partial indexes for active tournaments (MySQL doesn't support partial indexes, use filtered approach)
        // This is handled in application logic with compound indexes above

        // 7. R#M# notation lookup optimization
        Schema::table('bracket_matches', function (Blueprint $table) {
            if (!$this->indexExists('bracket_matches', 'idx_round_match_lookup')) {
                $table->index(['round_number', 'match_number', 'bracket_stage_id'], 'idx_round_match_lookup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop custom indexes first
        Schema::table('bracket_matches', function (Blueprint $table) {
            $table->dropIndex('idx_bracket_display_covering');
            $table->dropIndex('idx_live_matches');
            $table->dropIndex('idx_progression');
            $table->dropIndex('idx_round_match_lookup');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('idx_team_search');
            $table->dropIndex('idx_team_rankings');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('idx_player_search');
            $table->dropIndex('idx_player_stats');
        });

        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                $table->dropIndex('idx_match_stats_analysis');
                $table->dropIndex('idx_player_performance');
            });
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_active');
            $table->dropIndex('idx_events_featured');
        });

        // Drop JSON functional indexes if they exist
        if ($this->supportsFunctionalIndexes()) {
            DB::statement('DROP INDEX IF EXISTS idx_dependency_matches_gin ON bracket_matches');
            DB::statement('DROP INDEX IF EXISTS idx_map_veto_data_gin ON bracket_matches');
        }

        // Drop new tables
        Schema::dropIfExists('tournament_phases');
        Schema::dropIfExists('bracket_swiss_standings');

        // Remove new columns from bracket_matches
        Schema::table('bracket_matches', function (Blueprint $table) {
            $table->dropColumn([
                'liquipedia_id', 'dependency_matches', 'map_veto_data',
                'next_match_upper', 'next_match_lower', 'bracket_reset'
            ]);
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select(DB::raw("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'"));
        return !empty($indexes);
    }

    /**
     * Check if database supports functional indexes (MySQL 8.0+)
     */
    private function supportsFunctionalIndexes(): bool
    {
        $version = DB::select('SELECT VERSION() as version')[0]->version;
        return version_compare($version, '8.0.0', '>=');
    }
};