<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Admin Dashboard Performance Optimization
     * 
     * This migration adds comprehensive database optimizations specifically
     * for admin dashboard CRUD operations on players and teams tables.
     */
    public function up(): void
    {
        // ============================================================================
        // PLAYERS TABLE OPTIMIZATIONS
        // ============================================================================
        
        Schema::table('players', function (Blueprint $table) {
            // Composite index for admin dashboard listing with filters
            if (!$this->indexExists('players', 'idx_players_admin_listing')) {
                // Optimizes: ORDER BY rating DESC WHERE role = ? AND team_id = ? AND status = ?
                $table->index(['status', 'role', 'team_id', 'rating'], 'idx_players_admin_listing');
            }
            
            // Search optimization index
            if (!$this->indexExists('players', 'idx_players_search')) {
                // Optimizes: WHERE username LIKE ? OR real_name LIKE ?
                $table->index(['username', 'real_name'], 'idx_players_search');
            }
            
            // Team relationship optimization
            if (!$this->indexExists('players', 'idx_players_team_active')) {
                // Optimizes: WHERE team_id = ? AND status = 'active'
                $table->index(['team_id', 'status'], 'idx_players_team_active');
            }
            
            // Rating-based queries optimization
            if (!$this->indexExists('players', 'idx_players_rating_role')) {
                // Optimizes: WHERE role = ? ORDER BY rating DESC
                $table->index(['role', 'rating'], 'idx_players_rating_role');
            }
            
            // Region-based filtering
            if (!$this->indexExists('players', 'idx_players_region_rating')) {
                // Optimizes: WHERE region = ? ORDER BY rating DESC
                $table->index(['region', 'rating'], 'idx_players_region_rating');
            }
            
            // Admin dashboard pagination optimization
            if (!$this->indexExists('players', 'idx_players_admin_pagination')) {
                // Optimizes: pagination with common filters
                $table->index(['created_at', 'id'], 'idx_players_admin_pagination');
            }
            
            // Email-based lookups for admin operations
            if (!$this->indexExists('players', 'idx_players_email')) {
                // Optimizes: WHERE email = ? for admin user management
                $table->index('email', 'idx_players_email');
            }
        });

        // ============================================================================
        // TEAMS TABLE OPTIMIZATIONS  
        // ============================================================================
        
        Schema::table('teams', function (Blueprint $table) {
            // Composite index for admin dashboard listing with filters
            if (!$this->indexExists('teams', 'idx_teams_admin_listing')) {
                // Optimizes: ORDER BY rating DESC WHERE region = ? AND platform = ? AND status = ?
                $table->index(['status', 'region', 'platform', 'rating'], 'idx_teams_admin_listing');
            }
            
            // Search optimization index
            if (!$this->indexExists('teams', 'idx_teams_search')) {
                // Optimizes: WHERE name LIKE ? OR short_name LIKE ?
                $table->index(['name', 'short_name'], 'idx_teams_search');
            }
            
            // Ranking and performance queries
            if (!$this->indexExists('teams', 'idx_teams_ranking')) {
                // Optimizes: WHERE matches_played > 0 ORDER BY rating DESC
                $table->index(['matches_played', 'rating'], 'idx_teams_ranking');
            }
            
            // Region-platform optimization
            if (!$this->indexExists('teams', 'idx_teams_region_platform_rating')) {
                // Optimizes: WHERE region = ? AND platform = ? ORDER BY rating DESC
                $table->index(['region', 'platform', 'rating'], 'idx_teams_region_platform_rating');
            }
            
            // Country-based queries
            if (!$this->indexExists('teams', 'idx_teams_country_rating')) {
                // Optimizes: WHERE country = ? ORDER BY rating DESC
                $table->index(['country', 'rating'], 'idx_teams_country_rating');
            }
            
            // Admin dashboard pagination optimization
            if (!$this->indexExists('teams', 'idx_teams_admin_pagination')) {
                // Optimizes: pagination with common filters
                $table->index(['created_at', 'id'], 'idx_teams_admin_pagination');
            }
            
            // Earnings-based queries for admin analytics
            if (!$this->indexExists('teams', 'idx_teams_earnings')) {
                // Optimizes: WHERE earnings_amount > 0 ORDER BY earnings_amount DESC
                $table->index(['earnings_amount', 'earnings_currency'], 'idx_teams_earnings');
            }
        });

        // ============================================================================
        // MATCHES TABLE OPTIMIZATIONS FOR ADMIN DASHBOARD
        // ============================================================================
        
        if (Schema::hasTable('matches')) {
            Schema::table('matches', function (Blueprint $table) {
                // Live scoring optimization
                if (!$this->indexExists('matches', 'idx_matches_admin_live_scoring')) {
                    // Optimizes: WHERE status IN ('live', 'upcoming') ORDER BY scheduled_at
                    $table->index(['status', 'scheduled_at'], 'idx_matches_admin_live_scoring');
                }
                
                // Team-based match queries
                if (!$this->indexExists('matches', 'idx_matches_teams_date')) {
                    // Optimizes: WHERE (team1_id = ? OR team2_id = ?) ORDER BY scheduled_at
                    $table->index(['team1_id', 'team2_id', 'scheduled_at'], 'idx_matches_teams_date');
                }
                
                // Event-based queries
                if (!$this->indexExists('matches', 'idx_matches_event_status_date')) {
                    // Optimizes: WHERE event_id = ? AND status = ? ORDER BY scheduled_at
                    $table->index(['event_id', 'status', 'scheduled_at'], 'idx_matches_event_status_date');
                }
                
                // Completed matches for statistics
                if (!$this->indexExists('matches', 'idx_matches_completed_date')) {
                    // Optimizes: WHERE status = 'completed' ORDER BY completed_at DESC
                    $table->index(['status', 'completed_at'], 'idx_matches_completed_date');
                }
            });
        }

        // ============================================================================
        // USER ACTIVITIES TABLE OPTIMIZATIONS FOR ADMIN ANALYTICS
        // ============================================================================
        
        if (Schema::hasTable('user_activities')) {
            Schema::table('user_activities', function (Blueprint $table) {
                // Admin analytics optimization
                if (!$this->indexExists('user_activities', 'idx_user_activities_admin_analytics')) {
                    // Optimizes: WHERE created_at >= ? GROUP BY action
                    $table->index(['created_at', 'action'], 'idx_user_activities_admin_analytics');
                }
                
                // User-based activity tracking
                if (!$this->indexExists('user_activities', 'idx_user_activities_user_recent')) {
                    // Optimizes: WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
                    $table->index(['user_id', 'created_at'], 'idx_user_activities_user_recent');
                }
                
                // Activity type analysis
                if (!$this->indexExists('user_activities', 'idx_user_activities_type_date')) {
                    // Optimizes: WHERE activity_type = ? AND created_at >= ?
                    $table->index(['activity_type', 'created_at'], 'idx_user_activities_type_date');
                }
            });
        }

        // ============================================================================
        // PLAYER TEAM HISTORY OPTIMIZATIONS
        // ============================================================================
        
        if (Schema::hasTable('player_team_history')) {
            Schema::table('player_team_history', function (Blueprint $table) {
                // Player history optimization
                if (!$this->indexExists('player_team_history', 'idx_player_team_history_player_date')) {
                    // Optimizes: WHERE player_id = ? ORDER BY change_date DESC
                    $table->index(['player_id', 'change_date'], 'idx_player_team_history_player_date');
                }
                
                // Team history optimization
                if (!$this->indexExists('player_team_history', 'idx_player_team_history_teams_date')) {
                    // Optimizes: WHERE to_team_id = ? OR from_team_id = ? ORDER BY change_date DESC
                    $table->index(['to_team_id', 'from_team_id', 'change_date'], 'idx_player_team_history_teams_date');
                }
            });
        }

        // ============================================================================
        // MATCH PLAYER STATS OPTIMIZATIONS
        // ============================================================================
        
        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                // Player performance analysis
                if (!$this->indexExists('match_player_stats', 'idx_match_player_stats_player_match')) {
                    // Optimizes: WHERE player_id = ? ORDER BY created_at DESC
                    $table->index(['player_id', 'match_id', 'created_at'], 'idx_match_player_stats_player_match');
                }
                
                // Hero statistics
                if (!$this->indexExists('match_player_stats', 'idx_match_player_stats_hero')) {
                    // Optimizes: WHERE hero_played = ? AND player_id = ?
                    $table->index(['hero_played', 'player_id'], 'idx_match_player_stats_hero');
                }
                
                // Match-based stats loading
                if (!$this->indexExists('match_player_stats', 'idx_match_player_stats_match_team')) {
                    // Optimizes: WHERE match_id = ? ORDER BY team_id, player_id
                    $table->index(['match_id', 'team_id', 'player_id'], 'idx_match_player_stats_match_team');
                }
            });
        }

        // ============================================================================
        // NEWS AND CONTENT OPTIMIZATIONS FOR ADMIN DASHBOARD
        // ============================================================================
        
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                // Admin content management
                if (!$this->indexExists('news', 'idx_news_admin_management')) {
                    // Optimizes: WHERE status = ? ORDER BY created_at DESC
                    $table->index(['status', 'created_at'], 'idx_news_admin_management');
                }
                
                // Author-based queries
                if (!$this->indexExists('news', 'idx_news_author_date')) {
                    // Optimizes: WHERE author_id = ? ORDER BY created_at DESC
                    $table->index(['author_id', 'created_at'], 'idx_news_author_date');
                }
                
                // Category and featured content
                if (!$this->indexExists('news', 'idx_news_category_featured')) {
                    // Optimizes: WHERE category_id = ? AND featured = ? AND status = ?
                    $table->index(['category_id', 'featured', 'status'], 'idx_news_category_featured');
                }
            });
        }

        // ============================================================================
        // EVENTS TABLE OPTIMIZATIONS
        // ============================================================================
        
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                // Admin event management
                if (!$this->indexExists('events', 'idx_events_admin_management')) {
                    // Optimizes: WHERE status = ? ORDER BY start_date DESC
                    $table->index(['status', 'start_date'], 'idx_events_admin_management');
                }
                
                // Event type and tier filtering
                if (!$this->indexExists('events', 'idx_events_type_tier_date')) {
                    // Optimizes: WHERE type = ? AND tier = ? ORDER BY start_date DESC
                    $table->index(['type', 'tier', 'start_date'], 'idx_events_type_tier_date');
                }
                
                // Featured and ongoing events
                if (!$this->indexExists('events', 'idx_events_featured_status')) {
                    // Optimizes: WHERE featured = ? AND status IN (?)
                    $table->index(['featured', 'status'], 'idx_events_featured_status');
                }
            });
        }

        // ============================================================================
        // FORUM OPTIMIZATIONS FOR ADMIN MODERATION
        // ============================================================================
        
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                // Admin moderation queries
                if (!$this->indexExists('forum_threads', 'idx_forum_threads_moderation')) {
                    // Optimizes: WHERE status = ? OR reported = ? ORDER BY updated_at DESC
                    $table->index(['status', 'reported', 'updated_at'], 'idx_forum_threads_moderation');
                }
                
                // User content management
                if (!$this->indexExists('forum_threads', 'idx_forum_threads_user_status')) {
                    // Optimizes: WHERE user_id = ? AND status = ? ORDER BY created_at DESC
                    $table->index(['user_id', 'status', 'created_at'], 'idx_forum_threads_user_status');
                }
            });
        }

        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                // Admin moderation queries
                if (!$this->indexExists('forum_posts', 'idx_forum_posts_moderation')) {
                    // Optimizes: WHERE status = ? OR reported = ? ORDER BY updated_at DESC
                    $table->index(['status', 'reported', 'updated_at'], 'idx_forum_posts_moderation');
                }
            });
        }

        // ============================================================================
        // CREATE MATERIALIZED VIEWS FOR HEAVY ADMIN QUERIES
        // ============================================================================
        
        // Admin dashboard statistics view
        $this->createAdminStatsView();
        
        // Rankings cache table
        $this->createRankingsCacheTable();
        
        // Performance metrics cache
        $this->createPerformanceMetricsCache();
        
        DB::statement('ANALYZE TABLE players, teams, matches, user_activities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop materialized views and cache tables
        DB::statement('DROP VIEW IF EXISTS admin_dashboard_stats');
        Schema::dropIfExists('rankings_cache');
        Schema::dropIfExists('performance_metrics_cache');
        
        // Drop indexes from players table
        Schema::table('players', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'idx_players_admin_listing');
            $this->dropIndexIfExists($table, 'idx_players_search');
            $this->dropIndexIfExists($table, 'idx_players_team_active');
            $this->dropIndexIfExists($table, 'idx_players_rating_role');
            $this->dropIndexIfExists($table, 'idx_players_region_rating');
            $this->dropIndexIfExists($table, 'idx_players_admin_pagination');
            $this->dropIndexIfExists($table, 'idx_players_email');
        });

        // Drop indexes from teams table
        Schema::table('teams', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'idx_teams_admin_listing');
            $this->dropIndexIfExists($table, 'idx_teams_search');
            $this->dropIndexIfExists($table, 'idx_teams_ranking');
            $this->dropIndexIfExists($table, 'idx_teams_region_platform_rating');
            $this->dropIndexIfExists($table, 'idx_teams_country_rating');
            $this->dropIndexIfExists($table, 'idx_teams_admin_pagination');
            $this->dropIndexIfExists($table, 'idx_teams_earnings');
        });

        // Drop other indexes...
        if (Schema::hasTable('matches')) {
            Schema::table('matches', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_matches_admin_live_scoring');
                $this->dropIndexIfExists($table, 'idx_matches_teams_date');
                $this->dropIndexIfExists($table, 'idx_matches_event_status_date');
                $this->dropIndexIfExists($table, 'idx_matches_completed_date');
            });
        }

        if (Schema::hasTable('user_activities')) {
            Schema::table('user_activities', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_user_activities_admin_analytics');
                $this->dropIndexIfExists($table, 'idx_user_activities_user_recent');
                $this->dropIndexIfExists($table, 'idx_user_activities_type_date');
            });
        }

        // Continue dropping other indexes...
    }

    /**
     * Create admin dashboard statistics view for fast queries
     */
    private function createAdminStatsView(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW admin_dashboard_stats AS
            SELECT 
                "users" as metric_type,
                (SELECT COUNT(*) FROM users) as total_count,
                (SELECT COUNT(*) FROM users WHERE DATE(last_login) = CURDATE()) as active_today,
                (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)) as new_this_week,
                NOW() as last_updated
            UNION ALL
            SELECT 
                "teams" as metric_type,
                (SELECT COUNT(*) FROM teams) as total_count,
                (SELECT COUNT(*) FROM teams WHERE status = "active") as active_today,
                (SELECT COUNT(*) FROM teams WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)) as new_this_week,
                NOW() as last_updated
            UNION ALL
            SELECT 
                "players" as metric_type,
                (SELECT COUNT(*) FROM players) as total_count,
                (SELECT COUNT(*) FROM players WHERE status = "active") as active_today,
                (SELECT COUNT(*) FROM players WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)) as new_this_week,
                NOW() as last_updated
            UNION ALL
            SELECT 
                "matches" as metric_type,
                (SELECT COUNT(*) FROM matches) as total_count,
                (SELECT COUNT(*) FROM matches WHERE status = "live") as active_today,
                (SELECT COUNT(*) FROM matches WHERE DATE(scheduled_at) = CURDATE()) as new_this_week,
                NOW() as last_updated
        ');
    }

    /**
     * Create rankings cache table for fast leaderboard queries
     */
    private function createRankingsCacheTable(): void
    {
        Schema::create('rankings_cache', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['team', 'player'])->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('region', 20)->nullable()->index();
            $table->string('role', 20)->nullable()->index();
            $table->integer('rank')->index();
            $table->integer('rating');
            $table->json('metadata');
            $table->timestamp('last_updated')->index();
            
            $table->unique(['type', 'entity_id', 'region', 'role'], 'unique_ranking_entry');
            $table->index(['type', 'region', 'role', 'rank'], 'idx_rankings_type_filters');
        });
    }

    /**
     * Create performance metrics cache for admin analytics
     */
    private function createPerformanceMetricsCache(): void
    {
        Schema::create('performance_metrics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name', 100)->index();
            $table->string('entity_type', 50)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('time_period', 20)->index();
            $table->json('metric_data');
            $table->timestamp('calculated_at')->index();
            $table->timestamp('expires_at')->index();
            
            $table->unique(['metric_name', 'entity_type', 'entity_id', 'time_period'], 'unique_metric_cache');
        });
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Index doesn't exist, ignore
        }
    }
};