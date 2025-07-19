<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create forum post votes table if it doesn't exist
        if (!Schema::hasTable('forum_post_votes')) {
            Schema::create('forum_post_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('forum_posts')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['post_id', 'user_id']);
            });
        }
        
        // Create forum thread votes table if it doesn't exist
        if (!Schema::hasTable('forum_thread_votes')) {
            Schema::create('forum_thread_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->constrained('forum_threads')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['thread_id', 'user_id']);
            });
        }
        
        // Add missing columns to forum_posts
        Schema::table('forum_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_posts', 'score')) {
                $table->integer('score')->default(0)->after('downvotes');
            }
            if (!Schema::hasColumn('forum_posts', 'is_edited')) {
                $table->boolean('is_edited')->default(false)->after('score');
            }
            if (!Schema::hasColumn('forum_posts', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('is_edited');
            }
            if (!Schema::hasColumn('forum_posts', 'status')) {
                $table->enum('status', ['active', 'deleted', 'moderated'])->default('active')->after('edited_at');
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('forum_thread_votes');
        Schema::dropIfExists('forum_post_votes');
        
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['score', 'is_edited', 'edited_at', 'status']);
        });
    }
};