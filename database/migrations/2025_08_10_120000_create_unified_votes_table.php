<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Create unified votes table for all content types (news, forum, matches, etc.)
     */
    public function up()
    {
        // Create unified votes table
        if (!Schema::hasTable('votes')) {
            Schema::create('votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('voteable_type'); // 'news', 'news_comment', 'forum_thread', 'forum_post', 'match_comment'
                $table->unsignedBigInteger('voteable_id'); // ID of the content being voted on
                $table->tinyInteger('vote'); // 1 for upvote, -1 for downvote
                $table->timestamps();
                
                // Ensure unique vote per user per item
                $table->unique(['user_id', 'voteable_type', 'voteable_id'], 'votes_user_content_unique');
                $table->index(['voteable_type', 'voteable_id']);
                $table->index(['user_id', 'created_at']);
            });
            
            echo "✅ Created unified votes table\n";
        } else {
            echo "ℹ️ Unified votes table already exists\n";
        }
        
        // Add vote count columns to news table if they don't exist
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                if (!Schema::hasColumn('news', 'upvotes')) {
                    $table->integer('upvotes')->default(0)->after('content');
                }
                if (!Schema::hasColumn('news', 'downvotes')) {
                    $table->integer('downvotes')->default(0)->after('upvotes');
                }
                if (!Schema::hasColumn('news', 'score')) {
                    $table->integer('score')->default(0)->after('downvotes');
                }
            });
            echo "✅ Added vote columns to news table\n";
        }
        
        // Add vote count columns to news_comments table if they don't exist
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('news_comments', 'upvotes')) {
                    $table->integer('upvotes')->default(0)->after('content');
                }
                if (!Schema::hasColumn('news_comments', 'downvotes')) {
                    $table->integer('downvotes')->default(0)->after('upvotes');
                }
                if (!Schema::hasColumn('news_comments', 'score')) {
                    $table->integer('score')->default(0)->after('downvotes');
                }
            });
            echo "✅ Added vote columns to news_comments table\n";
        }
        
        // Add vote count columns to forum_threads table if they don't exist
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('forum_threads', 'upvotes')) {
                    $table->integer('upvotes')->default(0)->after('content');
                }
                if (!Schema::hasColumn('forum_threads', 'downvotes')) {
                    $table->integer('downvotes')->default(0)->after('upvotes');
                }
                if (!Schema::hasColumn('forum_threads', 'score')) {
                    $table->integer('score')->default(0)->after('downvotes');
                }
            });
            echo "✅ Added vote columns to forum_threads table\n";
        }
        
        // Add vote count columns to forum_posts table if they don't exist and if table exists
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                if (!Schema::hasColumn('forum_posts', 'upvotes')) {
                    $table->integer('upvotes')->default(0)->after('content');
                }
                if (!Schema::hasColumn('forum_posts', 'downvotes')) {
                    $table->integer('downvotes')->default(0)->after('upvotes');
                }
                if (!Schema::hasColumn('forum_posts', 'score')) {
                    $table->integer('score')->default(0)->after('downvotes');
                }
            });
            echo "✅ Added vote columns to forum_posts table\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Remove vote columns from content tables
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                $table->dropColumn(['upvotes', 'downvotes', 'score']);
            });
        }
        
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                $table->dropColumn(['upvotes', 'downvotes', 'score']);
            });
        }
        
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->dropColumn(['upvotes', 'downvotes', 'score']);
            });
        }
        
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->dropColumn(['upvotes', 'downvotes', 'score']);
            });
        }
        
        // Drop the unified votes table
        Schema::dropIfExists('votes');
    }
};