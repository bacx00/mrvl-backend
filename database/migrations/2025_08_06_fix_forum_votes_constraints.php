<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop existing problematic unique constraints if they exist
        try {
            Schema::table('forum_votes', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'post_id']);
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }
        
        try {
            Schema::table('forum_votes', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'thread_id', 'post_id']);
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        // Add proper unique constraints that handle NULL values correctly
        // For MySQL 8.0+ partial indexes
        try {
            DB::statement('ALTER TABLE forum_votes ADD UNIQUE KEY `forum_votes_user_thread_unique` (`user_id`, `thread_id`) WHERE `post_id` IS NULL');
        } catch (\Exception $e) {
            // If partial indexes aren't supported, add basic unique constraint
            try {
                Schema::table('forum_votes', function (Blueprint $table) {
                    $table->unique(['user_id', 'thread_id'], 'forum_votes_user_thread_unique');
                });
            } catch (\Exception $e2) {
                // Already exists, continue
            }
        }
        
        try {
            DB::statement('ALTER TABLE forum_votes ADD UNIQUE KEY `forum_votes_user_post_unique` (`user_id`, `post_id`) WHERE `post_id` IS NOT NULL');
        } catch (\Exception $e) {
            // If partial indexes aren't supported, add basic unique constraint
            try {
                Schema::table('forum_votes', function (Blueprint $table) {
                    $table->unique(['user_id', 'post_id'], 'forum_votes_user_post_unique');
                });
            } catch (\Exception $e2) {
                // Already exists, continue
            }
        }
    }

    public function down()
    {
        Schema::table('forum_votes', function (Blueprint $table) {
            $table->dropIndex('forum_votes_user_thread_unique');
            $table->dropIndex('forum_votes_user_post_unique');
            
            // Restore original constraints
            $table->unique(['user_id', 'post_id']);
            $table->unique(['user_id', 'thread_id', 'post_id']);
        });
    }
};