<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create content_flags table for flagging system
        if (!Schema::hasTable('content_flags')) {
            Schema::create('content_flags', function (Blueprint $table) {
                $table->id();
                $table->string('flaggable_type'); // news, comment, etc.
                $table->unsignedBigInteger('flaggable_id');
                $table->unsignedBigInteger('flagger_id');
                $table->enum('flag_type', ['inappropriate', 'spam', 'misleading', 'copyright', 'other']);
                $table->text('reason');
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->enum('status', ['pending', 'dismissed', 'upheld', 'escalated'])->default('pending');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['flaggable_type', 'flaggable_id']);
                $table->index(['status', 'priority']);
                $table->foreign('flagger_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Create moderation_logs table for tracking moderation actions
        if (!Schema::hasTable('moderation_logs')) {
            Schema::create('moderation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('moderator_id');
                $table->string('action'); // approve_news, reject_news, flag_news, etc.
                $table->string('target_type'); // news, comment, user, etc.
                $table->unsignedBigInteger('target_id');
                $table->text('reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable(); // Additional action-specific data
                $table->timestamp('created_at');

                $table->index(['target_type', 'target_id']);
                $table->index(['moderator_id', 'created_at']);
                $table->index('action');
                $table->foreign('moderator_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Create reports table for user reports
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->string('reportable_type'); // news_comment, forum_post, etc.
                $table->unsignedBigInteger('reportable_id');
                $table->unsignedBigInteger('reporter_id');
                $table->text('reason');
                $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['reportable_type', 'reportable_id']);
                $table->index('status');
                $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Ensure news_video_embeds table exists
        if (!Schema::hasTable('news_video_embeds')) {
            Schema::create('news_video_embeds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('news_id');
                $table->string('platform'); // youtube, twitch-clip, twitch-video, twitter, etc.
                $table->string('video_id')->nullable();
                $table->text('embed_url')->nullable();
                $table->text('original_url');
                $table->string('title')->nullable();
                $table->text('thumbnail')->nullable();
                $table->string('duration')->nullable(); // Format: "MM:SS" or "H:MM:SS"
                $table->json('metadata')->nullable(); // Platform-specific data
                $table->timestamps();

                $table->foreign('news_id')->references('id')->on('news')->onDelete('cascade');
                $table->index('news_id');
                $table->index('platform');
            });
        }

        // Add missing columns to news table if they don't exist
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                if (!Schema::hasColumn('news', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('region');
                }
                if (!Schema::hasColumn('news', 'featured_at')) {
                    $table->timestamp('featured_at')->nullable()->after('featured');
                }
                if (!Schema::hasColumn('news', 'meta_data')) {
                    $table->json('meta_data')->nullable()->after('tags');
                }
            });
        }

        // Ensure news_comments has all necessary columns
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('news_comments', 'status')) {
                    $table->enum('status', ['active', 'deleted', 'moderated'])->default('active')->after('parent_id');
                }
                if (!Schema::hasColumn('news_comments', 'is_edited')) {
                    $table->boolean('is_edited')->default(false)->after('content');
                }
                if (!Schema::hasColumn('news_comments', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable()->after('is_edited');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added columns from existing tables
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                $table->dropColumn(['sort_order', 'featured_at', 'meta_data']);
            });
        }

        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                $table->dropColumn(['status', 'is_edited', 'edited_at']);
            });
        }

        // Drop new tables
        Schema::dropIfExists('news_video_embeds');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('content_flags');
    }
};