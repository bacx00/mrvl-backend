<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for critical performance indexes.
     */
    public function up(): void
    {
        try {
            // Teams table indexes
            if (Schema::hasTable('teams')) {
                Schema::table('teams', function (Blueprint $table) {
                    if (!$this->indexExists('teams', 'idx_teams_rating_region')) {
                        $table->index(['rating', 'region'], 'idx_teams_rating_region');
                    }
                    if (!$this->indexExists('teams', 'idx_teams_region')) {
                        $table->index('region', 'idx_teams_region');
                    }
                });
            }

            // Players table indexes
            if (Schema::hasTable('players')) {
                Schema::table('players', function (Blueprint $table) {
                    if (!$this->indexExists('players', 'idx_players_team_rating')) {
                        $table->index(['team_id', 'rating'], 'idx_players_team_rating');
                    }
                    if (!$this->indexExists('players', 'idx_players_role')) {
                        $table->index('role', 'idx_players_role');
                    }
                    if (!$this->indexExists('players', 'idx_players_rating')) {
                        $table->index('rating', 'idx_players_rating');
                    }
                });
            }

            // Matches table indexes
            if (Schema::hasTable('matches')) {
                Schema::table('matches', function (Blueprint $table) {
                    if (!$this->indexExists('matches', 'idx_matches_teams')) {
                        $table->index(['team1_id', 'team2_id'], 'idx_matches_teams');
                    }
                    if (!$this->indexExists('matches', 'idx_matches_status_date')) {
                        $table->index(['status', 'created_at'], 'idx_matches_status_date');
                    }
                });
            }

            // News table indexes
            if (Schema::hasTable('news')) {
                Schema::table('news', function (Blueprint $table) {
                    if (!$this->indexExists('news', 'idx_news_published')) {
                        $table->index(['is_published', 'created_at'], 'idx_news_published');
                    }
                });
            }

            // Forum threads indexes
            if (Schema::hasTable('forum_threads')) {
                Schema::table('forum_threads', function (Blueprint $table) {
                    if (!$this->indexExists('forum_threads', 'idx_forum_user_date')) {
                        $table->index(['user_id', 'created_at'], 'idx_forum_user_date');
                    }
                });
            }

        } catch (\Exception $e) {
            \Log::error('Performance migration error: ' . $e->getMessage());
            // Don't fail the migration, just log the error
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Teams table
            if (Schema::hasTable('teams')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->dropIndex('idx_teams_rating_region');
                    $table->dropIndex('idx_teams_region');
                });
            }

            // Players table
            if (Schema::hasTable('players')) {
                Schema::table('players', function (Blueprint $table) {
                    $table->dropIndex('idx_players_team_rating');
                    $table->dropIndex('idx_players_role');
                    $table->dropIndex('idx_players_rating');
                });
            }

            // Matches table
            if (Schema::hasTable('matches')) {
                Schema::table('matches', function (Blueprint $table) {
                    $table->dropIndex('idx_matches_teams');
                    $table->dropIndex('idx_matches_status_date');
                });
            }

            // News table
            if (Schema::hasTable('news')) {
                Schema::table('news', function (Blueprint $table) {
                    $table->dropIndex('idx_news_published');
                });
            }

            // Forum threads
            if (Schema::hasTable('forum_threads')) {
                Schema::table('forum_threads', function (Blueprint $table) {
                    $table->dropIndex('idx_forum_user_date');
                });
            }
        } catch (\Exception $e) {
            \Log::error('Performance rollback error: ' . $e->getMessage());
        }
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists($table, $indexName): bool
    {
        try {
            $indexes = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }
};