<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create unified forum_votes table
        if (!Schema::hasTable('forum_votes')) {
            Schema::create('forum_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->nullable()->constrained('forum_threads')->onDelete('cascade');
                $table->foreignId('post_id')->nullable()->constrained('forum_posts')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                // Ensure unique vote per user per item
                $table->unique(['thread_id', 'post_id', 'user_id']);
                $table->index(['thread_id', 'vote_type']);
                $table->index(['post_id', 'vote_type']);
            });
        }
        
        // Create forum_posts table if it doesn't exist
        if (!Schema::hasTable('forum_posts')) {
            Schema::create('forum_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->constrained('forum_threads')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->text('content');
                $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->onDelete('cascade');
                $table->integer('upvotes')->default(0);
                $table->integer('downvotes')->default(0);
                $table->integer('score')->default(0);
                $table->boolean('is_edited')->default(false);
                $table->timestamp('edited_at')->nullable();
                $table->enum('status', ['active', 'deleted', 'moderated', 'reported'])->default('active');
                $table->boolean('reported')->default(false);
                $table->timestamps();
                
                $table->index(['thread_id', 'created_at']);
                $table->index(['parent_id']);
            });
        }
        
        // Add missing columns to forum_threads if needed
        Schema::table('forum_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_threads', 'replies_count')) {
                $table->integer('replies_count')->default(0)->after('views');
            }
            if (!Schema::hasColumn('forum_threads', 'status')) {
                $table->enum('status', ['active', 'moderated', 'reported'])->default('active')->after('locked');
            }
            if (!Schema::hasColumn('forum_threads', 'reported')) {
                $table->boolean('reported')->default(false)->after('status');
            }
            if (!Schema::hasColumn('forum_threads', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable()->after('reported');
            }
            if (!Schema::hasColumn('forum_threads', 'moderated_by')) {
                $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('forum_threads', 'moderation_reason')) {
                $table->text('moderation_reason')->nullable()->after('moderated_by');
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('forum_votes');
        Schema::dropIfExists('forum_posts');
        
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropColumn(['replies_count', 'status', 'reported', 'moderated_at', 'moderated_by', 'moderation_reason']);
        });
    }
};