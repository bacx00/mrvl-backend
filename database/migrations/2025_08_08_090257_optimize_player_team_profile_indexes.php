<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Optimize player_team_history table
        if (Schema::hasTable('player_team_history')) {
            Schema::table('player_team_history', function (Blueprint $table) {
                // Index for player team history lookups
                if (!$this->indexExists('player_team_history', 'idx_player_team_history_player_id')) {
                    $table->index(['player_id', 'change_date'], 'idx_player_team_history_player_id');
                }
                
                // Index for team history lookups
                if (!$this->indexExists('player_team_history', 'idx_player_team_history_teams')) {
                    $table->index(['from_team_id', 'to_team_id'], 'idx_player_team_history_teams');
                }
            });
        }

        // Optimize match_player_stats table
        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                // Index for player match statistics
                if (!$this->indexExists('match_player_stats', 'idx_match_player_stats_player_match')) {
                    $table->index(['player_id', 'match_id'], 'idx_match_player_stats_player_match');
                }
                
                // Index for hero-based queries
                if (!$this->indexExists('match_player_stats', 'idx_match_player_stats_player_hero')) {
                    $table->index(['player_id', 'hero'], 'idx_match_player_stats_player_hero');
                }
                
                // Index for performance queries
                if (!$this->indexExists('match_player_stats', 'idx_match_player_stats_performance')) {
                    $table->index(['player_id', 'mvp_score'], 'idx_match_player_stats_performance');
                }
            });
        }

        // Optimize matches table
        if (Schema::hasTable('matches')) {
            Schema::table('matches', function (Blueprint $table) {
                // Index for team-based match queries
                if (!$this->indexExists('matches', 'idx_matches_teams_status')) {
                    $table->index(['team1_id', 'team2_id', 'status'], 'idx_matches_teams_status');
                }
                
                // Index for event-based queries
                if (!$this->indexExists('matches', 'idx_matches_event_date')) {
                    $table->index(['event_id', 'date'], 'idx_matches_event_date');
                }
                
                // Index for status and date queries
                if (!$this->indexExists('matches', 'idx_matches_status_date')) {
                    $table->index(['status', 'date'], 'idx_matches_status_date');
                }
            });
        }

        // Optimize players table
        if (Schema::hasTable('players')) {
            Schema::table('players', function (Blueprint $table) {
                // Index for team roster queries
                if (!$this->indexExists('players', 'idx_players_team_status')) {
                    $table->index(['team_id', 'status'], 'idx_players_team_status');
                }
                
                // Index for rating-based queries
                if (!$this->indexExists('players', 'idx_players_rating_role')) {
                    $table->index(['rating', 'role'], 'idx_players_rating_role');
                }
            });
        }

        // Optimize teams table
        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                // Index for region and rating queries
                if (!$this->indexExists('teams', 'idx_teams_region_rating')) {
                    $table->index(['region', 'rating'], 'idx_teams_region_rating');
                }
                
                // Index for platform and status queries
                if (!$this->indexExists('teams', 'idx_teams_platform_status')) {
                    $table->index(['platform', 'status'], 'idx_teams_platform_status');
                }
            });
        }

        // Optimize events table
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                // Index for tier and date queries
                if (!$this->indexExists('events', 'idx_events_tier_date')) {
                    $table->index(['tier', 'start_date'], 'idx_events_tier_date');
                }
                
                // Index for status queries
                if (!$this->indexExists('events', 'idx_events_status_date')) {
                    $table->index(['status', 'end_date'], 'idx_events_status_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes in reverse order
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropIndex('idx_events_tier_date');
                $table->dropIndex('idx_events_status_date');
            });
        }

        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropIndex('idx_teams_region_rating');
                $table->dropIndex('idx_teams_platform_status');
            });
        }

        if (Schema::hasTable('players')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropIndex('idx_players_team_status');
                $table->dropIndex('idx_players_rating_role');
            });
        }

        if (Schema::hasTable('matches')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropIndex('idx_matches_teams_status');
                $table->dropIndex('idx_matches_event_date');
                $table->dropIndex('idx_matches_status_date');
            });
        }

        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                try { $table->dropIndex('idx_match_player_stats_player_match'); } catch (Exception $e) {}
                try { $table->dropIndex('idx_match_player_stats_player_hero'); } catch (Exception $e) {}
                try { $table->dropIndex('idx_match_player_stats_performance'); } catch (Exception $e) {}
            });
        }

        if (Schema::hasTable('player_team_history')) {
            Schema::table('player_team_history', function (Blueprint $table) {
                $table->dropIndex('idx_player_team_history_player_id');
                $table->dropIndex('idx_player_team_history_teams');
            });
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists($table, $indexName)
    {
        try {
            $indexes = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes($table);
            
            return isset($indexes[$indexName]);
        } catch (Exception $e) {
            return false;
        }
    }
};