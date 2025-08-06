<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        try {
            // First, fix the forum_votes table constraints
            Schema::table('forum_votes', function (Blueprint $table) {
                // Drop any problematic constraints
                try {
                    $table->dropUnique(['user_id', 'post_id']);
                } catch (\Exception $e) {
                    // Ignore if constraint doesn't exist
                }
                
                try {
                    $table->dropUnique(['user_id', 'thread_id', 'post_id']);
                } catch (\Exception $e) {
                    // Ignore if constraint doesn't exist
                }
                
                try {
                    $table->dropUnique(['thread_id', 'post_id', 'user_id']);
                } catch (\Exception $e) {
                    // Ignore if constraint doesn't exist
                }
            });

            // Create proper unique constraints for voting
            // One constraint for thread votes (where post_id is NULL)
            DB::statement('
                CREATE UNIQUE INDEX `forum_votes_user_thread_unique` 
                ON `forum_votes` (`user_id`, `thread_id`) 
                WHERE `post_id` IS NULL
            ');
            
            // One constraint for post votes (where post_id is NOT NULL)  
            DB::statement('
                CREATE UNIQUE INDEX `forum_votes_user_post_unique` 
                ON `forum_votes` (`user_id`, `post_id`) 
                WHERE `post_id` IS NOT NULL
            ');

        } catch (\Exception $e) {
            // If MySQL doesn't support filtered indexes, use alternative approach
            Schema::table('forum_votes', function (Blueprint $table) {
                // Ensure we have proper indexes for queries
                $table->index(['thread_id', 'user_id', 'post_id']);
                $table->index(['post_id', 'user_id']);
                $table->index(['user_id', 'thread_id']);
                $table->index(['vote_type']);
            });
        }

        // Ensure forum_posts table has all required columns
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
            if (!Schema::hasColumn('forum_posts', 'is_edited')) {
                $table->boolean('is_edited')->default(false);
            }
            if (!Schema::hasColumn('forum_posts', 'edited_at')) {
                $table->timestamp('edited_at')->nullable();
            }
            if (!Schema::hasColumn('forum_posts', 'status')) {
                $table->enum('status', ['active', 'deleted', 'moderated', 'reported'])->default('active');
            }
            if (!Schema::hasColumn('forum_posts', 'reported')) {
                $table->boolean('reported')->default(false);
            }
            if (!Schema::hasColumn('forum_posts', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable();
            }
            if (!Schema::hasColumn('forum_posts', 'moderated_by')) {
                $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('forum_posts', 'moderation_reason')) {
                $table->text('moderation_reason')->nullable();
            }
        });

        // Ensure forum_threads table has all required columns for proper functionality
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
            if (!Schema::hasColumn('forum_threads', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('forum_categories')->onDelete('set null');
            }
            if (!Schema::hasColumn('forum_threads', 'status')) {
                $table->enum('status', ['active', 'deleted', 'moderated', 'reported'])->default('active');
            }
            if (!Schema::hasColumn('forum_threads', 'reported')) {
                $table->boolean('reported')->default(false);
            }
            if (!Schema::hasColumn('forum_threads', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable();
            }
            if (!Schema::hasColumn('forum_threads', 'moderated_by')) {
                $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('forum_threads', 'moderation_reason')) {
                $table->text('moderation_reason')->nullable();
            }
        });

        // Create mentions table if it doesn't exist
        if (!Schema::hasTable('mentions')) {
            Schema::create('mentions', function (Blueprint $table) {
                $table->id();
                $table->string('mentionable_type'); // 'forum_thread', 'forum_post', 'news_article', etc.
                $table->unsignedBigInteger('mentionable_id');
                $table->enum('mentioned_type', ['user', 'team', 'player']);
                $table->unsignedBigInteger('mentioned_id');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Who made the mention
                $table->boolean('is_read')->default(false);
                $table->timestamp('mentioned_at');
                $table->timestamps();

                $table->index(['mentionable_type', 'mentionable_id']);
                $table->index(['mentioned_type', 'mentioned_id']);
                $table->index(['user_id']);
                $table->index(['is_read']);
            });
        }

        // Create forum_reports table for proper report management
        if (!Schema::hasTable('forum_reports')) {
            Schema::create('forum_reports', function (Blueprint $table) {
                $table->id();
                $table->enum('reportable_type', ['forum_thread', 'forum_post']);
                $table->unsignedBigInteger('reportable_id');
                $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
                $table->string('reason', 500);
                $table->text('details')->nullable();
                $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('reviewed_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                $table->index(['reportable_type', 'reportable_id']);
                $table->index(['reported_by']);
                $table->index(['status']);
                $table->index(['reviewed_by']);
            });
        }

        // Ensure forum_categories table exists and has proper structure
        if (!Schema::hasTable('forum_categories')) {
            Schema::create('forum_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->string('color', 7)->default('#6b7280');
                $table->string('icon', 50)->default('📝');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            // Add default categories
            DB::table('forum_categories')->insert([
                [
                    'name' => 'General Discussion',
                    'slug' => 'general',
                    'description' => 'General discussion about Marvel Rivals',
                    'color' => '#6b7280',
                    'icon' => '💬',
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Strategy & Tips',
                    'slug' => 'strategy',
                    'description' => 'Share strategies, tips, and gameplay advice',
                    'color' => '#10b981',
                    'icon' => '🎯',
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Competitive',
                    'slug' => 'competitive',
                    'description' => 'Competitive scene discussion and tournament talk',
                    'color' => '#ef4444',
                    'icon' => '🏆',
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Team Recruitment',
                    'slug' => 'recruitment',
                    'description' => 'Looking for team members or join a team',
                    'color' => '#8b5cf6',
                    'icon' => '👥',
                    'sort_order' => 4,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'name' => 'Bug Reports',
                    'slug' => 'bugs',
                    'description' => 'Report bugs and issues with the game',
                    'color' => '#f59e0b',
                    'icon' => '🐛',
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }

        echo "Forum system migration completed successfully!\n";

    }

    public function down()
    {
        // Drop the custom indexes
        try {
            DB::statement('DROP INDEX `forum_votes_user_thread_unique` ON `forum_votes`');
            DB::statement('DROP INDEX `forum_votes_user_post_unique` ON `forum_votes`');
        } catch (\Exception $e) {
            // Ignore if indexes don't exist
        }

        // Drop created tables
        Schema::dropIfExists('forum_reports');
        Schema::dropIfExists('mentions');

        // Remove added columns from existing tables
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropColumn(['upvotes', 'downvotes', 'score', 'category_id', 'status', 'reported', 'moderated_at', 'moderated_by', 'moderation_reason']);
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['upvotes', 'downvotes', 'score', 'is_edited', 'edited_at', 'status', 'reported', 'moderated_at', 'moderated_by', 'moderation_reason']);
        });
    }
};