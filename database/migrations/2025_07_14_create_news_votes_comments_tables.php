<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create news_comments table if it doesn't exist
        if (!Schema::hasTable('news_comments')) {
            Schema::create('news_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('news_id')->constrained('news')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->text('content');
                $table->foreignId('parent_id')->nullable()->constrained('news_comments')->onDelete('cascade');
                $table->integer('upvotes')->default(0);
                $table->integer('downvotes')->default(0);
                $table->integer('score')->default(0);
                $table->boolean('is_edited')->default(false);
                $table->timestamp('edited_at')->nullable();
                $table->enum('status', ['active', 'deleted', 'moderated'])->default('active');
                $table->timestamps();
                
                $table->index(['news_id', 'created_at']);
                $table->index(['parent_id']);
            });
        }
        
        // Create news_votes table if it doesn't exist
        if (!Schema::hasTable('news_votes')) {
            Schema::create('news_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('news_id')->constrained('news')->onDelete('cascade');
                $table->foreignId('comment_id')->nullable()->constrained('news_comments')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                // Ensure unique vote per user per item
                $table->unique(['news_id', 'comment_id', 'user_id']);
                $table->index(['news_id', 'vote_type']);
                $table->index(['comment_id', 'vote_type']);
            });
        }
        
        // Add missing columns to news table
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'comments_count')) {
                $table->integer('comments_count')->default(0)->after('views');
            }
            if (!Schema::hasColumn('news', 'upvotes')) {
                $table->integer('upvotes')->default(0)->after('comments_count');
            }
            if (!Schema::hasColumn('news', 'downvotes')) {
                $table->integer('downvotes')->default(0)->after('upvotes');
            }
            if (!Schema::hasColumn('news', 'score')) {
                $table->integer('score')->default(0)->after('downvotes');
            }
            if (!Schema::hasColumn('news', 'breaking')) {
                $table->boolean('breaking')->default(false)->after('featured');
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('news_votes');
        Schema::dropIfExists('news_comments');
        
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['comments_count', 'upvotes', 'downvotes', 'score', 'breaking']);
        });
    }
};