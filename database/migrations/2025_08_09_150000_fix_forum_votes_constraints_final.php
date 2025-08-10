<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, ensure the forum_votes table exists with the correct structure
        if (!Schema::hasTable('forum_votes')) {
            Schema::create('forum_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->nullable()->constrained('forum_threads')->onDelete('cascade');
                $table->foreignId('post_id')->nullable()->constrained('forum_posts')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                // Add proper indexes
                $table->index(['thread_id', 'vote_type']);
                $table->index(['post_id', 'vote_type']);
                $table->index(['user_id']);
            });
        }

        // Drop all existing problematic unique constraints
        try {
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_thread_id_post_id_user_id_unique`');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }
        
        try {
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_id_post_id_unique`');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }
        
        try {
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_thread_unique`');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }
        
        try {
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_post_unique`');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        // Clean up any duplicate votes that might exist
        $this->removeDuplicateVotes();

        // Add proper unique constraints that handle NULL values correctly
        // For thread votes (post_id is NULL)
        try {
            DB::statement('
                ALTER TABLE forum_votes 
                ADD UNIQUE KEY `forum_votes_user_thread_vote_unique` (`user_id`, `thread_id`) 
                WHERE `post_id` IS NULL
            ');
        } catch (\Exception $e) {
            // MySQL version might not support partial indexes, use regular unique constraint
            try {
                // Remove any existing thread votes duplicates first
                DB::statement('
                    DELETE v1 FROM forum_votes v1
                    INNER JOIN forum_votes v2
                    WHERE v1.id > v2.id
                    AND v1.user_id = v2.user_id
                    AND v1.thread_id = v2.thread_id
                    AND v1.post_id IS NULL
                    AND v2.post_id IS NULL
                ');
                
                // Add composite unique index for thread votes
                DB::statement('ALTER TABLE forum_votes ADD UNIQUE KEY `forum_votes_user_thread_null_unique` (`user_id`, `thread_id`, `post_id`)');
            } catch (\Exception $e2) {
                // If this fails, we'll handle it with application logic
            }
        }

        // For post votes (post_id is NOT NULL)
        try {
            DB::statement('
                ALTER TABLE forum_votes 
                ADD UNIQUE KEY `forum_votes_user_post_vote_unique` (`user_id`, `post_id`) 
                WHERE `post_id` IS NOT NULL
            ');
        } catch (\Exception $e) {
            // MySQL version might not support partial indexes, use regular unique constraint
            try {
                // Remove any existing post votes duplicates first
                DB::statement('
                    DELETE v1 FROM forum_votes v1
                    INNER JOIN forum_votes v2
                    WHERE v1.id > v2.id
                    AND v1.user_id = v2.user_id
                    AND v1.post_id = v2.post_id
                    AND v1.post_id IS NOT NULL
                    AND v2.post_id IS NOT NULL
                ');
                
                // Add unique index for post votes
                DB::statement('ALTER TABLE forum_votes ADD UNIQUE KEY `forum_votes_user_post_unique` (`user_id`, `post_id`)');
            } catch (\Exception $e2) {
                // If this fails, we'll handle it with application logic
            }
        }

        // Ensure forum_threads has vote count columns
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('forum_threads', 'upvotes')) {
                    $table->integer('upvotes')->default(0);
                }
                if (!Schema::hasColumn('forum_threads', 'downvotes')) {
                    $table->integer('downvotes')->default(0);
                }
                if (!Schema::hasColumn('forum_threads', 'score')) {
                    $table->integer('score')->default(0);
                }
            });
        }

        // Ensure forum_posts has vote count columns
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                if (!Schema::hasColumn('forum_posts', 'upvotes')) {
                    $table->integer('upvotes')->default(0);
                }
                if (!Schema::hasColumn('forum_posts', 'downvotes')) {
                    $table->integer('downvotes')->default(0);
                }
                if (!Schema::hasColumn('forum_posts', 'score')) {
                    $table->integer('score')->default(0);
                }
            });
        }

        // Update vote counts for existing data
        $this->updateVoteCounts();
    }

    public function down()
    {
        // Remove the unique constraints we added
        try {
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_thread_vote_unique`');
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_post_vote_unique`');
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_thread_null_unique`');
            DB::statement('ALTER TABLE forum_votes DROP INDEX IF EXISTS `forum_votes_user_post_unique`');
        } catch (\Exception $e) {
            // Ignore errors on down migration
        }
    }

    /**
     * Remove duplicate votes from the database
     */
    private function removeDuplicateVotes()
    {
        // Remove duplicate thread votes (keep the latest one)
        DB::statement('
            DELETE v1 FROM forum_votes v1
            INNER JOIN forum_votes v2
            WHERE v1.id < v2.id
            AND v1.user_id = v2.user_id
            AND v1.thread_id = v2.thread_id
            AND v1.post_id IS NULL
            AND v2.post_id IS NULL
        ');

        // Remove duplicate post votes (keep the latest one)
        DB::statement('
            DELETE v1 FROM forum_votes v1
            INNER JOIN forum_votes v2
            WHERE v1.id < v2.id
            AND v1.user_id = v2.user_id
            AND v1.post_id = v2.post_id
            AND v1.post_id IS NOT NULL
            AND v2.post_id IS NOT NULL
        ');
    }

    /**
     * Update vote counts for existing threads and posts
     */
    private function updateVoteCounts()
    {
        // Update thread vote counts
        DB::statement('
            UPDATE forum_threads ft
            SET 
                upvotes = (
                    SELECT COUNT(*) 
                    FROM forum_votes fv 
                    WHERE fv.thread_id = ft.id 
                    AND fv.post_id IS NULL 
                    AND fv.vote_type = "upvote"
                ),
                downvotes = (
                    SELECT COUNT(*) 
                    FROM forum_votes fv 
                    WHERE fv.thread_id = ft.id 
                    AND fv.post_id IS NULL 
                    AND fv.vote_type = "downvote"
                ),
                score = (
                    SELECT 
                        COALESCE(SUM(CASE WHEN fv.vote_type = "upvote" THEN 1 ELSE -1 END), 0)
                    FROM forum_votes fv 
                    WHERE fv.thread_id = ft.id 
                    AND fv.post_id IS NULL
                )
        ');

        // Update post vote counts
        DB::statement('
            UPDATE forum_posts fp
            SET 
                upvotes = (
                    SELECT COUNT(*) 
                    FROM forum_votes fv 
                    WHERE fv.post_id = fp.id 
                    AND fv.vote_type = "upvote"
                ),
                downvotes = (
                    SELECT COUNT(*) 
                    FROM forum_votes fv 
                    WHERE fv.post_id = fp.id 
                    AND fv.vote_type = "downvote"
                ),
                score = (
                    SELECT 
                        COALESCE(SUM(CASE WHEN fv.vote_type = "upvote" THEN 1 ELSE -1 END), 0)
                    FROM forum_votes fv 
                    WHERE fv.post_id = fp.id
                )
        ');
    }
};