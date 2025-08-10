<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Comprehensive Tournament Database Performance Optimization
     * 
     * This migration implements advanced database optimizations for tournament operations:
     * 1. High-performance indexes for all critical query patterns
     * 2. Real-time optimization structures
     * 3. Swiss system performance enhancements
     * 4. Bracket query optimizations
     * 5. Cache-friendly data structures
     * 6. Scalability preparations
     * 7. JSON query optimizations
     * 8. Covering indexes for hot paths
     */
    public function up(): void
    {
        // ===== CORE TOURNAMENT OPTIMIZATIONS =====
        
        // 1. Tournament lookup optimizations
        Schema::table('tournaments', function (Blueprint $table) {
            // Hot path: Tournament list filtering
            if (!$this->indexExists('tournaments', 'idx_tournaments_hot_path')) {
                $table->index(['status', 'featured', 'public', 'start_date'], 'idx_tournaments_hot_path');
            }
            
            // Tournament search and filtering
            if (!$this->indexExists('tournaments', 'idx_tournaments_search')) {
                $table->index(['type', 'format', 'region', 'status'], 'idx_tournaments_search');
            }
            
            // Registration window queries
            if (!$this->indexExists('tournaments', 'idx_tournaments_registration')) {
                $table->index(['registration_start', 'registration_end', 'status'], 'idx_tournaments_registration');
            }
            
            // Live tournament queries
            if (!$this->indexExists('tournaments', 'idx_tournaments_live')) {
                $table->index(['status', 'current_phase', 'start_date'], 'idx_tournaments_live');
            }
            
            // Organizer dashboard queries
            if (!$this->indexExists('tournaments', 'idx_tournaments_organizer')) {
                $table->index(['organizer_id', 'status', 'created_at'], 'idx_tournaments_organizer');
            }
        });

        // 2. Tournament Teams pivot table optimization
        Schema::table('tournament_teams', function (Blueprint $table) {
            // Swiss standings calculation (most critical)
            if (!$this->indexExists('tournament_teams', 'idx_swiss_standings_optimized')) {
                $table->index([
                    'tournament_id', 'status', 'swiss_wins', 'swiss_losses', 'swiss_buchholz'
                ], 'idx_swiss_standings_optimized');
            }
            
            // Bracket positioning
            if (!$this->indexExists('tournament_teams', 'idx_bracket_positioning')) {
                $table->index(['tournament_id', 'bracket_position', 'seed'], 'idx_bracket_positioning');
            }
            
            // Team registration status tracking
            if (!$this->indexExists('tournament_teams', 'idx_registration_status')) {
                $table->index(['tournament_id', 'status', 'registered_at'], 'idx_registration_status');
            }
            
            // Prize and placement queries
            if (!$this->indexExists('tournament_teams', 'idx_results_leaderboard')) {
                $table->index(['tournament_id', 'placement', 'prize_money'], 'idx_results_leaderboard');
            }
        });

        // 3. Tournament Registration optimizations
        if (Schema::hasTable('tournament_registrations')) {
            Schema::table('tournament_registrations', function (Blueprint $table) {
                // Registration approval workflow
                if (!$this->indexExists('tournament_registrations', 'idx_registration_workflow')) {
                    $table->index(['tournament_id', 'status', 'registered_at'], 'idx_registration_workflow');
                }
                
                // Team registration lookup
                if (!$this->indexExists('tournament_registrations', 'idx_team_registration')) {
                    $table->index(['team_id', 'status', 'tournament_id'], 'idx_team_registration');
                }
                
                // User registration history
                if (!$this->indexExists('tournament_registrations', 'idx_user_registrations')) {
                    $table->index(['user_id', 'registered_at', 'status'], 'idx_user_registrations');
                }
            });
        }

        // 4. Tournament Phases optimization
        if (Schema::hasTable('tournament_phases')) {
            Schema::table('tournament_phases', function (Blueprint $table) {
                // Phase progression queries
                if (!$this->indexExists('tournament_phases', 'idx_phase_progression')) {
                    $table->index(['tournament_id', 'phase_order', 'status'], 'idx_phase_progression');
                }
                
                // Active phase lookup
                if (!$this->indexExists('tournament_phases', 'idx_active_phases')) {
                    $table->index(['tournament_id', 'is_active', 'start_date'], 'idx_active_phases');
                }
            });
        }

        // ===== BRACKET SYSTEM OPTIMIZATIONS =====
        
        // 5. Bracket Matches - Critical for real-time updates
        Schema::table('bracket_matches', function (Blueprint $table) {
            // Live scoring hot path (most critical query)
            if (!$this->indexExists('bracket_matches', 'idx_live_scoring_critical')) {
                $table->index(['tournament_id', 'status', 'scheduled_at', 'started_at'], 'idx_live_scoring_critical');
            }
            
            // Bracket display optimization
            if (!$this->indexExists('bracket_matches', 'idx_bracket_display_optimized')) {
                $table->index(['bracket_stage_id', 'round', 'match_number'], 'idx_bracket_display_optimized');
            }
            
            // Tournament progression tracking
            if (!$this->indexExists('bracket_matches', 'idx_tournament_progression')) {
                $table->index(['tournament_id', 'round', 'status', 'completed_at'], 'idx_tournament_progression');
            }
            
            // Team match history
            if (!$this->indexExists('bracket_matches', 'idx_team_match_history')) {
                $table->index(['team1_id', 'team2_id', 'tournament_id'], 'idx_team_match_history');
            }
            
            // Match dependencies (for bracket progression)
            if (!$this->indexExists('bracket_matches', 'idx_match_dependencies')) {
                $table->index(['next_match_upper', 'next_match_lower', 'round'], 'idx_match_dependencies');
            }
        });

        // 6. Swiss System Specialized Table
        if (Schema::hasTable('bracket_swiss_standings')) {
            Schema::table('bracket_swiss_standings', function (Blueprint $table) {
                // Swiss pairing optimization (critical for large tournaments)
                if (!$this->indexExists('bracket_swiss_standings', 'idx_swiss_pairing_optimized')) {
                    $table->index([
                        'bracket_id', 'current_round', 'wins', 'losses', 'buchholz_score'
                    ], 'idx_swiss_pairing_optimized');
                }
                
                // Qualification tracking
                if (!$this->indexExists('bracket_swiss_standings', 'idx_swiss_qualification')) {
                    $table->index(['bracket_id', 'qualified', 'eliminated', 'wins'], 'idx_swiss_qualification');
                }
                
                // Tiebreaker calculations
                if (!$this->indexExists('bracket_swiss_standings', 'idx_swiss_tiebreakers')) {
                    $table->index([
                        'bracket_id', 'match_win_percentage', 'opponent_match_win_percentage'
                    ], 'idx_swiss_tiebreakers');
                }
            });
        }

        // ===== MATCH AND PLAYER OPTIMIZATIONS =====
        
        // 7. Match Player Stats optimization
        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                // Player performance analysis
                if (!$this->indexExists('match_player_stats', 'idx_player_performance_analysis')) {
                    $table->index(['player_id', 'hero_name', 'tournament_id'], 'idx_player_performance_analysis');
                }
                
                // Match statistics aggregation
                if (!$this->indexExists('match_player_stats', 'idx_match_stats_aggregation')) {
                    $table->index(['match_id', 'team_id', 'map_number'], 'idx_match_stats_aggregation');
                }
                
                // Tournament hero meta analysis
                if (!$this->indexExists('match_player_stats', 'idx_tournament_hero_meta')) {
                    $table->index(['tournament_id', 'hero_name', 'kills', 'deaths'], 'idx_tournament_hero_meta');
                }
            });
        }

        // 8. Teams optimization for tournament queries
        Schema::table('teams', function (Blueprint $table) {
            // Tournament team lookup (with region filtering)
            if (!$this->indexExists('teams', 'idx_tournament_teams_lookup')) {
                $table->index(['region', 'status', 'ranking', 'name'], 'idx_tournament_teams_lookup');
            }
            
            // Team performance metrics
            if (!$this->indexExists('teams', 'idx_team_performance_metrics')) {
                $table->index(['wins', 'losses', 'elo_rating', 'earnings'], 'idx_team_performance_metrics');
            }
        });

        // 9. Players optimization
        Schema::table('players', function (Blueprint $table) {
            // Player tournament history
            if (!$this->indexExists('players', 'idx_player_tournament_history')) {
                $table->index(['team_id', 'role', 'elo_rating'], 'idx_player_tournament_history');
            }
        });

        // ===== COVERING INDEXES FOR CRITICAL QUERIES =====
        
        // 10. Create covering indexes for most common queries
        if ($this->supportsCoveringIndexes()) {
            // Tournament list with essential data
            DB::statement("
                CREATE INDEX idx_tournaments_list_covering 
                ON tournaments (status, featured, public, start_date) 
                INCLUDE (id, name, type, format, region, prize_pool, team_count, max_teams)
            ");
            
            // Swiss standings covering index
            DB::statement("
                CREATE INDEX idx_swiss_complete_covering 
                ON tournament_teams (tournament_id, status, swiss_wins DESC, swiss_losses ASC, swiss_buchholz DESC) 
                INCLUDE (team_id, seed, placement, swiss_score, points_earned)
            ");
            
            // Live match covering index
            DB::statement("
                CREATE INDEX idx_live_matches_covering 
                ON bracket_matches (tournament_id, status, started_at) 
                INCLUDE (id, team1_id, team2_id, team1_score, team2_score, round, match_number, scheduled_at)
            ");
        }

        // ===== FUNCTIONAL INDEXES FOR JSON QUERIES =====
        
        // 11. JSON functional indexes (MySQL 8.0+)
        if ($this->supportsFunctionalIndexes()) {
            // Tournament settings quick access
            DB::statement("
                CREATE INDEX idx_tournament_settings_json 
                ON tournaments ((CAST(settings->>'$.format' AS CHAR(50))))
            ");
            
            // Phase data access
            DB::statement("
                CREATE INDEX idx_tournament_phase_data_json 
                ON tournaments ((CAST(phase_data->>'$.current_round' AS UNSIGNED)))
            ");
            
            // Bracket data navigation
            DB::statement("
                CREATE INDEX idx_bracket_data_json 
                ON tournaments ((CAST(bracket_data->>'$.type' AS CHAR(30))))
            ");
            
            // Match dependency tracking
            if (Schema::hasTable('bracket_matches')) {
                DB::statement("
                    CREATE INDEX idx_match_dependency_json 
                    ON bracket_matches ((CAST(dependency_matches AS JSON)))
                ");
            }
        }

        // ===== PARTITIONING PREPARATION =====
        
        // 12. Add partitioning helper columns
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'partition_date')) {
                $table->date('partition_date')->nullable()->after('end_date');
                $table->index('partition_date');
            }
        });

        if (Schema::hasTable('bracket_matches')) {
            Schema::table('bracket_matches', function (Blueprint $table) {
                if (!Schema::hasColumn('bracket_matches', 'partition_date')) {
                    $table->date('partition_date')->nullable()->after('completed_at');
                    $table->index('partition_date');
                }
            });
        }

        // ===== REAL-TIME OPTIMIZATION STRUCTURES =====
        
        // 13. Create tournament cache metadata table
        if (!Schema::hasTable('tournament_cache_metadata')) {
            Schema::create('tournament_cache_metadata', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->string('cache_key', 100);
                $table->enum('cache_type', [
                    'bracket', 'standings', 'statistics', 'live_data', 'leaderboard'
                ]);
                $table->timestamp('last_updated');
                $table->timestamp('expires_at')->nullable();
                $table->json('invalidation_triggers')->nullable();
                $table->boolean('is_dirty')->default(false);
                $table->integer('access_count')->default(0);
                $table->timestamps();
                
                $table->unique(['tournament_id', 'cache_key']);
                $table->index(['cache_type', 'last_updated']);
                $table->index(['expires_at', 'is_dirty']);
                $table->index(['tournament_id', 'cache_type', 'is_dirty']);
            });
        }

        // 14. Tournament follower optimization
        if (Schema::hasTable('tournament_followers')) {
            Schema::table('tournament_followers', function (Blueprint $table) {
                // Notification delivery optimization
                if (!$this->indexExists('tournament_followers', 'idx_follower_notifications')) {
                    $table->index(['tournament_id', 'user_id', 'notification_preferences'], 'idx_follower_notifications');
                }
                
                // User tournament tracking
                if (!$this->indexExists('tournament_followers', 'idx_user_following')) {
                    $table->index(['user_id', 'followed_at', 'tournament_id'], 'idx_user_following');
                }
            });
        }

        // ===== ANALYTICS AND MONITORING OPTIMIZATIONS =====
        
        // 15. Query performance monitoring table
        if (!Schema::hasTable('tournament_query_performance')) {
            Schema::create('tournament_query_performance', function (Blueprint $table) {
                $table->id();
                $table->string('query_type', 50);
                $table->string('query_hash', 64)->index();
                $table->text('query_pattern');
                $table->integer('execution_count')->default(1);
                $table->decimal('avg_execution_time', 8, 3);
                $table->decimal('max_execution_time', 8, 3);
                $table->decimal('min_execution_time', 8, 3);
                $table->json('execution_plan')->nullable();
                $table->integer('rows_examined_avg');
                $table->integer('rows_sent_avg');
                $table->timestamp('first_seen');
                $table->timestamp('last_seen');
                $table->boolean('needs_optimization')->default(false);
                $table->timestamps();
                
                $table->index(['query_type', 'avg_execution_time']);
                $table->index(['needs_optimization', 'avg_execution_time']);
                $table->index(['last_seen', 'execution_count']);
            });
        }

        // ===== HISTORICAL DATA OPTIMIZATION =====
        
        // 16. Tournament archive indicators
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
                $table->index(['archived_at', 'completed_at']);
            }
            
            if (!Schema::hasColumn('tournaments', 'archive_priority')) {
                $table->tinyInteger('archive_priority')->default(0);
                $table->index('archive_priority');
            }
        });

        // Update partition dates for existing records
        DB::statement("
            UPDATE tournaments 
            SET partition_date = DATE(COALESCE(end_date, start_date, created_at))
            WHERE partition_date IS NULL
        ");

        if (Schema::hasTable('bracket_matches')) {
            DB::statement("
                UPDATE bracket_matches 
                SET partition_date = DATE(COALESCE(completed_at, started_at, scheduled_at, created_at))
                WHERE partition_date IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop performance monitoring table
        Schema::dropIfExists('tournament_query_performance');
        Schema::dropIfExists('tournament_cache_metadata');

        // Remove archive columns
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'archive_priority', 'partition_date']);
        });

        if (Schema::hasTable('bracket_matches')) {
            Schema::table('bracket_matches', function (Blueprint $table) {
                $table->dropColumn('partition_date');
            });
        }

        // Drop functional indexes
        if ($this->supportsFunctionalIndexes()) {
            DB::statement('DROP INDEX IF EXISTS idx_tournament_settings_json ON tournaments');
            DB::statement('DROP INDEX IF EXISTS idx_tournament_phase_data_json ON tournaments');
            DB::statement('DROP INDEX IF EXISTS idx_bracket_data_json ON tournaments');
            
            if (Schema::hasTable('bracket_matches')) {
                DB::statement('DROP INDEX IF EXISTS idx_match_dependency_json ON bracket_matches');
            }
        }

        // Drop covering indexes
        if ($this->supportsCoveringIndexes()) {
            DB::statement('DROP INDEX IF EXISTS idx_tournaments_list_covering ON tournaments');
            DB::statement('DROP INDEX IF EXISTS idx_swiss_complete_covering ON tournament_teams');
            DB::statement('DROP INDEX IF EXISTS idx_live_matches_covering ON bracket_matches');
        }

        // Drop regular indexes (Laravel will handle this automatically when dropping columns)
        $tables = [
            'tournaments' => [
                'idx_tournaments_hot_path', 'idx_tournaments_search', 
                'idx_tournaments_registration', 'idx_tournaments_live', 'idx_tournaments_organizer'
            ],
            'tournament_teams' => [
                'idx_swiss_standings_optimized', 'idx_bracket_positioning',
                'idx_registration_status', 'idx_results_leaderboard'
            ],
            'bracket_matches' => [
                'idx_live_scoring_critical', 'idx_bracket_display_optimized',
                'idx_tournament_progression', 'idx_team_match_history', 'idx_match_dependencies'
            ]
        ];

        foreach ($tables as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($indexes) {
                    foreach ($indexes as $index) {
                        try {
                            $tableBlueprint->dropIndex($index);
                        } catch (\Exception $e) {
                            // Index might not exist, continue
                        }
                    }
                });
            }
        }
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if database supports covering indexes
     */
    private function supportsCoveringIndexes(): bool
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            // MySQL 8.0+ supports covering indexes with INCLUDE
            return version_compare($version, '8.0.13', '>=');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if database supports functional indexes (MySQL 8.0+)
     */
    private function supportsFunctionalIndexes(): bool
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            return version_compare($version, '8.0.0', '>=');
        } catch (\Exception $e) {
            return false;
        }
    }
};