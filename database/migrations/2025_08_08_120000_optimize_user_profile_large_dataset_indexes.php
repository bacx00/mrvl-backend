<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add covering indexes for large dataset performance optimization
        
        // Covering index for user profile queries with team flair data
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'idx_users_profile_covering')) {
                // Covering index that includes commonly selected columns
                DB::statement('CREATE INDEX idx_users_profile_covering ON users (id, team_flair_id, hero_flair) 
                    INCLUDE (name, email, avatar, show_hero_flair, show_team_flair, use_hero_as_avatar, status, last_login, created_at)');
            }
        });
        
        // Covering index for team flair lookups
        Schema::table('teams', function (Blueprint $table) {
            if (!$this->indexExists('teams', 'idx_teams_flair_covering')) {
                DB::statement('CREATE INDEX idx_teams_flair_covering ON teams (id) 
                    INCLUDE (name, short_name, logo, region)');
            }
        });
        
        // Optimized indexes for activity aggregation queries
        Schema::table('news_comments', function (Blueprint $table) {
            if (!$this->indexExists('news_comments', 'idx_news_comments_stats_optimized')) {
                $table->index(['user_id', 'id'], 'idx_news_comments_stats_optimized');
            }
        });
        
        Schema::table('match_comments', function (Blueprint $table) {
            if (!$this->indexExists('match_comments', 'idx_match_comments_stats_optimized')) {
                $table->index(['user_id', 'id'], 'idx_match_comments_stats_optimized');
            }
        });
        
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                if (!$this->indexExists('forum_posts', 'idx_forum_posts_stats_optimized')) {
                    $table->index(['user_id', 'id'], 'idx_forum_posts_stats_optimized');
                }
            });
        }
        
        if (Schema::hasTable('votes')) {
            Schema::table('votes', function (Blueprint $table) {
                if (!$this->indexExists('votes', 'idx_votes_stats_optimized')) {
                    $table->index(['user_id', 'vote', 'id'], 'idx_votes_stats_optimized');
                }
            });
        }
        
        // Composite indexes for activity feed queries
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                if (!$this->indexExists('news_comments', 'idx_news_comments_activity_feed')) {
                    DB::statement('CREATE INDEX idx_news_comments_activity_feed ON news_comments (user_id, created_at DESC) 
                        INCLUDE (id, news_id, content)');
                }
            });
        }
        
        if (Schema::hasTable('match_comments')) {
            Schema::table('match_comments', function (Blueprint $table) {
                if (!$this->indexExists('match_comments', 'idx_match_comments_activity_feed')) {
                    DB::statement('CREATE INDEX idx_match_comments_activity_feed ON match_comments (user_id, created_at DESC) 
                        INCLUDE (id, match_id, content)');
                }
            });
        }
        
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                if (!$this->indexExists('forum_threads', 'idx_forum_threads_activity_feed')) {
                    DB::statement('CREATE INDEX idx_forum_threads_activity_feed ON forum_threads (user_id, created_at DESC) 
                        INCLUDE (id, title, category_id, views)');
                }
            });
        }
        
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                if (!$this->indexExists('forum_posts', 'idx_forum_posts_activity_feed')) {
                    DB::statement('CREATE INDEX idx_forum_posts_activity_feed ON forum_posts (user_id, created_at DESC) 
                        INCLUDE (id, thread_id, content)');
                }
            });
        }
        
        // Partial indexes for active users (users with recent activity)
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_users_active_profiles 
            ON users (id, team_flair_id, hero_flair) 
            WHERE last_login > NOW() - INTERVAL 30 DAY');
            
        // Expression index for user avatar display logic
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_display_avatar 
            ON users ((CASE WHEN use_hero_as_avatar THEN hero_flair ELSE avatar END))');
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop custom indexes
        DB::statement('DROP INDEX IF EXISTS idx_users_profile_covering');
        DB::statement('DROP INDEX IF EXISTS idx_teams_flair_covering');
        DB::statement('DROP INDEX IF EXISTS idx_news_comments_activity_feed');
        DB::statement('DROP INDEX IF EXISTS idx_match_comments_activity_feed');
        DB::statement('DROP INDEX IF EXISTS idx_forum_threads_activity_feed');
        DB::statement('DROP INDEX IF EXISTS idx_forum_posts_activity_feed');
        DB::statement('DROP INDEX IF EXISTS idx_users_active_profiles');
        DB::statement('DROP INDEX IF EXISTS idx_users_display_avatar');
        
        // Drop standard indexes
        Schema::table('news_comments', function (Blueprint $table) {
            $table->dropIndex('idx_news_comments_stats_optimized');
        });
        
        Schema::table('match_comments', function (Blueprint $table) {
            $table->dropIndex('idx_match_comments_stats_optimized');
        });
        
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->dropIndex('idx_forum_posts_stats_optimized');
            });
        }
        
        if (Schema::hasTable('votes')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->dropIndex('idx_votes_stats_optimized');
            });
        }
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
};