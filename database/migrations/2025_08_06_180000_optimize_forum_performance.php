<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create optimized indexes for forum performance
        try {
            // Forum threads optimization
            Schema::table('forum_threads', function (Blueprint $table) {
                // Index for listing threads with sorting
                $table->index(['category', 'pinned', 'last_reply_at'], 'idx_forum_threads_listing');
                $table->index(['status', 'created_at'], 'idx_forum_threads_status');
                $table->index(['user_id', 'created_at'], 'idx_forum_threads_user');
                
                // Full-text search index
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE forum_threads ADD FULLTEXT idx_forum_threads_search (title, content)');
                }
                
                // Computed columns for performance
                if (!Schema::hasColumn('forum_threads', 'activity_score')) {
                    $table->decimal('activity_score', 8, 2)->default(0)->index();
                }
            });

            // Forum posts optimization
            Schema::table('forum_posts', function (Blueprint $table) {
                // Index for thread posts retrieval
                $table->index(['thread_id', 'parent_id', 'created_at'], 'idx_forum_posts_thread');
                $table->index(['user_id', 'created_at'], 'idx_forum_posts_user');
                $table->index(['status', 'created_at'], 'idx_forum_posts_status');
                
                // Full-text search index
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE forum_posts ADD FULLTEXT idx_forum_posts_search (content)');
                }
            });

            // Forum votes optimization
            Schema::table('forum_votes', function (Blueprint $table) {
                // Composite indexes for vote counting
                $table->index(['thread_id', 'vote_type'], 'idx_forum_votes_thread');
                $table->index(['post_id', 'vote_type'], 'idx_forum_votes_post');
                $table->index(['user_id', 'created_at'], 'idx_forum_votes_user');
            });

            // Users table optimization for forum features
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasIndex('users', 'idx_users_forum')) {
                    $table->index(['name', 'status'], 'idx_users_forum');
                }
            });

            // Mentions table optimization
            if (Schema::hasTable('mentions')) {
                Schema::table('mentions', function (Blueprint $table) {
                    // Compound index for mention queries
                    $table->index(['mentioned_type', 'mentioned_id', 'is_read'], 'idx_mentions_lookup');
                    $table->index(['mentionable_type', 'mentionable_id'], 'idx_mentions_source');
                });
            }

            // Create materialized view for hot threads
            DB::statement("
                CREATE OR REPLACE VIEW hot_forum_threads AS
                SELECT 
                    ft.id,
                    ft.title,
                    ft.user_id,
                    ft.category,
                    ft.replies_count,
                    ft.views,
                    ft.score,
                    ft.pinned,
                    ft.locked,
                    ft.created_at,
                    ft.last_reply_at,
                    (ft.score * 0.4 + ft.replies_count * 0.3 + (ft.views / 100) * 0.2 + 
                     CASE WHEN ft.last_reply_at > NOW() - INTERVAL 24 HOUR THEN 0.1 ELSE 0 END) as activity_score
                FROM forum_threads ft
                WHERE ft.status = 'active'
                ORDER BY ft.pinned DESC, activity_score DESC, ft.last_reply_at DESC
            ");

            echo "Forum performance optimization completed!\n";

        } catch (\Exception $e) {
            echo "Error during forum optimization: " . $e->getMessage() . "\n";
            // Continue with other optimizations even if some fail
        }
    }

    public function down()
    {
        // Remove indexes
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropIndex('idx_forum_threads_listing');
            $table->dropIndex('idx_forum_threads_status');
            $table->dropIndex('idx_forum_threads_user');
            $table->dropColumn('activity_score');
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropIndex('idx_forum_posts_thread');
            $table->dropIndex('idx_forum_posts_user');
            $table->dropIndex('idx_forum_posts_status');
        });

        Schema::table('forum_votes', function (Blueprint $table) {
            $table->dropIndex('idx_forum_votes_thread');
            $table->dropIndex('idx_forum_votes_post');
            $table->dropIndex('idx_forum_votes_user');
        });

        // Drop full-text indexes
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE forum_threads DROP INDEX idx_forum_threads_search');
                DB::statement('ALTER TABLE forum_posts DROP INDEX idx_forum_posts_search');
            } catch (\Exception $e) {
                // Ignore if indexes don't exist
            }
        }

        // Drop materialized view
        DB::statement('DROP VIEW IF EXISTS hot_forum_threads');
    }
};