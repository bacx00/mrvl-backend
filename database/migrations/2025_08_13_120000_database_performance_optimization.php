<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Critical Database Performance Optimization
     */
    public function up(): void
    {
        // Critical indexes for teams table
        Schema::table('teams', function (Blueprint $table) {
            // Primary performance indexes
            if (!$this->indexExists('teams', 'idx_teams_rating_rank')) {
                $table->index(['rating', 'rank'], 'idx_teams_rating_rank');
            }
            if (!$this->indexExists('teams', 'idx_teams_region_rating')) {
                $table->index(['region', 'rating'], 'idx_teams_region_rating');
            }
            if (!$this->indexExists('teams', 'idx_teams_platform_region')) {
                $table->index(['platform', 'region'], 'idx_teams_platform_region');
            }
            if (!$this->indexExists('teams', 'idx_teams_status_rating')) {
                $table->index(['status', 'rating'], 'idx_teams_status_rating');
            }
        });

        // Critical indexes for players table
        Schema::table('players', function (Blueprint $table) {
            // Foreign key and performance indexes
            if (!$this->indexExists('players', 'idx_players_team_rating')) {
                $table->index(['team_id', 'rating'], 'idx_players_team_rating');
            }
            if (!$this->indexExists('players', 'idx_players_role_rating')) {
                $table->index(['role', 'rating'], 'idx_players_role_rating');
            }
            if (!$this->indexExists('players', 'idx_players_region_rating')) {
                $table->index(['region', 'rating'], 'idx_players_region_rating');
            }
            if (!$this->indexExists('players', 'idx_players_status_rating')) {
                $table->index(['status', 'rating'], 'idx_players_status_rating');
            }
        });

        // Critical indexes for matches table
        Schema::table('matches', function (Blueprint $table) {
            // Performance critical indexes
            if (!$this->indexExists('matches', 'idx_matches_status_scheduled')) {
                $table->index(['status', 'scheduled_at'], 'idx_matches_status_scheduled');
            }
            if (!$this->indexExists('matches', 'idx_matches_teams_status')) {
                $table->index(['team1_id', 'team2_id', 'status'], 'idx_matches_teams_status');
            }
            if (!$this->indexExists('matches', 'idx_matches_event_status')) {
                $table->index(['event_id', 'status'], 'idx_matches_event_status');
            }
            if (!$this->indexExists('matches', 'idx_matches_winner_completed')) {
                $table->index(['winner_id', 'status'], 'idx_matches_winner_completed');
            }
        });

        // Match player stats optimization
        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                if (!$this->indexExists('match_player_stats', 'idx_match_stats_player_match')) {
                    $table->index(['player_id', 'match_id'], 'idx_match_stats_player_match');
                }
                if (!$this->indexExists('match_player_stats', 'idx_match_stats_match_team')) {
                    $table->index(['match_id', 'team_id'], 'idx_match_stats_match_team');
                }
                if (!$this->indexExists('match_player_stats', 'idx_match_stats_performance')) {
                    $table->index(['player_id', 'performance_rating'], 'idx_match_stats_performance');
                }
            });
        }

        // Events table optimization
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!$this->indexExists('events', 'idx_events_status_featured')) {
                    $table->index(['status', 'featured'], 'idx_events_status_featured');
                }
                if (!$this->indexExists('events', 'idx_events_dates')) {
                    $table->index(['start_date', 'end_date'], 'idx_events_dates');
                }
                if (!$this->indexExists('events', 'idx_events_tier_status')) {
                    $table->index(['tier', 'status'], 'idx_events_tier_status');
                }
            });
        }

        // News performance optimization
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                if (!$this->indexExists('news', 'idx_news_status_published')) {
                    $table->index(['status', 'published_at'], 'idx_news_status_published');
                }
                if (!$this->indexExists('news', 'idx_news_category_published')) {
                    $table->index(['category_id', 'published_at'], 'idx_news_category_published');
                }
                if (!$this->indexExists('news', 'idx_news_featured_published')) {
                    $table->index(['featured', 'published_at'], 'idx_news_featured_published');
                }
            });
        }

        // Forum performance optimization
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                if (!$this->indexExists('forum_threads', 'idx_threads_category_updated')) {
                    $table->index(['category_id', 'updated_at'], 'idx_threads_category_updated');
                }
                if (!$this->indexExists('forum_threads', 'idx_threads_pinned_updated')) {
                    $table->index(['pinned', 'updated_at'], 'idx_threads_pinned_updated');
                }
                if (!$this->indexExists('forum_threads', 'idx_threads_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_threads_user_created');
                }
            });
        }

        // Comments performance optimization
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                if (!$this->indexExists('news_comments', 'idx_comments_news_created')) {
                    $table->index(['news_id', 'created_at'], 'idx_comments_news_created');
                }
                if (!$this->indexExists('news_comments', 'idx_comments_parent_created')) {
                    $table->index(['parent_id', 'created_at'], 'idx_comments_parent_created');
                }
            });
        }

        if (Schema::hasTable('match_comments')) {
            Schema::table('match_comments', function (Blueprint $table) {
                if (!$this->indexExists('match_comments', 'idx_match_comments_match_created')) {
                    $table->index(['match_id', 'created_at'], 'idx_match_comments_match_created');
                }
            });
        }

        // User activities optimization (for admin dashboard)
        if (Schema::hasTable('user_activities')) {
            Schema::table('user_activities', function (Blueprint $table) {
                if (!$this->indexExists('user_activities', 'idx_activities_user_action')) {
                    $table->index(['user_id', 'action', 'created_at'], 'idx_activities_user_action');
                }
            });
        }

        // Voting system optimization
        if (Schema::hasTable('votes')) {
            Schema::table('votes', function (Blueprint $table) {
                if (!$this->indexExists('votes', 'idx_votes_voteable_type')) {
                    $table->index(['voteable_type', 'voteable_id', 'vote'], 'idx_votes_voteable_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Remove performance indexes
        $this->dropIndexIfExists('teams', 'idx_teams_rating_rank');
        $this->dropIndexIfExists('teams', 'idx_teams_region_rating');
        $this->dropIndexIfExists('teams', 'idx_teams_platform_region');
        $this->dropIndexIfExists('teams', 'idx_teams_status_rating');

        $this->dropIndexIfExists('players', 'idx_players_team_rating');
        $this->dropIndexIfExists('players', 'idx_players_role_rating');
        $this->dropIndexIfExists('players', 'idx_players_region_rating');
        $this->dropIndexIfExists('players', 'idx_players_status_rating');

        $this->dropIndexIfExists('matches', 'idx_matches_status_scheduled');
        $this->dropIndexIfExists('matches', 'idx_matches_teams_status');
        $this->dropIndexIfExists('matches', 'idx_matches_event_status');
        $this->dropIndexIfExists('matches', 'idx_matches_winner_completed');

        if (Schema::hasTable('match_player_stats')) {
            $this->dropIndexIfExists('match_player_stats', 'idx_match_stats_player_match');
            $this->dropIndexIfExists('match_player_stats', 'idx_match_stats_match_team');
            $this->dropIndexIfExists('match_player_stats', 'idx_match_stats_performance');
        }

        if (Schema::hasTable('events')) {
            $this->dropIndexIfExists('events', 'idx_events_status_featured');
            $this->dropIndexIfExists('events', 'idx_events_dates');
            $this->dropIndexIfExists('events', 'idx_events_tier_status');
        }
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            // For SQLite
            if (DB::getDriverName() === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list('{$table}')");
                foreach ($indexes as $index) {
                    if ($index->name === $indexName) {
                        return true;
                    }
                }
                return false;
            }
            
            // For MySQL/MariaDB
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (\Exception $e) {
                // Index may have already been dropped
            }
        }
    }
};