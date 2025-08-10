<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComprehensiveForumModerationTables extends Migration
{
    public function up()
    {
        // Create reports table for content reporting system
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reporter_id');
                $table->string('reportable_type');
                $table->unsignedBigInteger('reportable_id');
                $table->string('reason');
                $table->text('description')->nullable();
                $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
                $table->unsignedBigInteger('moderator_id')->nullable();
                $table->string('resolution')->nullable();
                $table->text('resolution_reason')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('moderator_id')->references('id')->on('users')->onDelete('set null');
                $table->index(['reportable_type', 'reportable_id']);
                $table->index(['status']);
                $table->index(['created_at']);
            });
        }

        // Create user warnings table for user moderation
        if (!Schema::hasTable('user_warnings')) {
            Schema::create('user_warnings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('moderator_id');
                $table->text('reason');
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->timestamp('expires_at')->nullable();
                $table->boolean('acknowledged')->default(false);
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('moderator_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['user_id']);
                $table->index(['expires_at']);
            });
        }

        // Add moderation fields to forum_threads table if they don't exist
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('forum_threads', 'is_flagged')) {
                    $table->boolean('is_flagged')->default(false)->after('locked');
                }
                if (!Schema::hasColumn('forum_threads', 'sticky')) {
                    $table->boolean('sticky')->default(false)->after('pinned');
                }
                if (!Schema::hasColumn('forum_threads', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable()->after('user_id');
                    $table->foreign('category_id')->references('id')->on('forum_categories')->onDelete('set null');
                }
                if (!Schema::hasColumn('forum_threads', 'moderation_note')) {
                    $table->text('moderation_note')->nullable();
                }
                if (!Schema::hasColumn('forum_threads', 'last_moderated_at')) {
                    $table->timestamp('last_moderated_at')->nullable();
                }
                if (!Schema::hasColumn('forum_threads', 'last_moderated_by')) {
                    $table->unsignedBigInteger('last_moderated_by')->nullable();
                    $table->foreign('last_moderated_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        // Add moderation fields to forum_posts table if they don't exist
        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                if (!Schema::hasColumn('forum_posts', 'is_flagged')) {
                    $table->boolean('is_flagged')->default(false);
                }
                if (!Schema::hasColumn('forum_posts', 'moderation_note')) {
                    $table->text('moderation_note')->nullable();
                }
                if (!Schema::hasColumn('forum_posts', 'last_moderated_at')) {
                    $table->timestamp('last_moderated_at')->nullable();
                }
                if (!Schema::hasColumn('forum_posts', 'last_moderated_by')) {
                    $table->unsignedBigInteger('last_moderated_by')->nullable();
                    $table->foreign('last_moderated_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        // Add moderation fields to users table if they don't exist
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'banned_at')) {
                    $table->timestamp('banned_at')->nullable();
                }
                if (!Schema::hasColumn('users', 'ban_reason')) {
                    $table->text('ban_reason')->nullable();
                }
                if (!Schema::hasColumn('users', 'ban_expires_at')) {
                    $table->timestamp('ban_expires_at')->nullable();
                }
                if (!Schema::hasColumn('users', 'muted_until')) {
                    $table->timestamp('muted_until')->nullable();
                }
                if (!Schema::hasColumn('users', 'last_activity')) {
                    $table->timestamp('last_activity')->nullable();
                }
                if (!Schema::hasColumn('users', 'warning_count')) {
                    $table->integer('warning_count')->default(0);
                }
            });
        }

        // Create moderation actions log table (if user_activities doesn't handle this)
        if (!Schema::hasTable('moderation_actions')) {
            Schema::create('moderation_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('moderator_id');
                $table->string('action_type');
                $table->string('target_type');
                $table->unsignedBigInteger('target_id');
                $table->json('metadata')->nullable();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->foreign('moderator_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['moderator_id']);
                $table->index(['target_type', 'target_id']);
                $table->index(['created_at']);
            });
        }

        // Add indexes for better performance
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->index(['is_flagged']);
                $table->index(['sticky']);
                $table->index(['locked']);
                $table->index(['pinned']);
                $table->index(['category_id']);
                $table->index(['created_at']);
                $table->index(['updated_at']);
            });
        }

        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->index(['is_flagged']);
                $table->index(['thread_id']);
                $table->index(['user_id']);
                $table->index(['created_at']);
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['banned_at']);
                $table->index(['muted_until']);
                $table->index(['last_activity']);
                $table->index(['warning_count']);
            });
        }
    }

    public function down()
    {
        // Remove added columns and indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['banned_at', 'ban_reason', 'ban_expires_at', 'muted_until', 'last_activity', 'warning_count']);
            });
        }

        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->dropColumn(['is_flagged', 'moderation_note', 'last_moderated_at', 'last_moderated_by']);
            });
        }

        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->dropColumn(['is_flagged', 'sticky', 'category_id', 'moderation_note', 'last_moderated_at', 'last_moderated_by']);
            });
        }

        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('user_warnings');
        Schema::dropIfExists('reports');
    }
}