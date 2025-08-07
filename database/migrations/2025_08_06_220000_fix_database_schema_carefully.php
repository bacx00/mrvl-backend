<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        echo "Starting careful database schema fixes...\n";

        // 1. Add missing 'videos' column to news table
        if (Schema::hasTable('news') && !Schema::hasColumn('news', 'videos')) {
            Schema::table('news', function (Blueprint $table) {
                $table->json('videos')->nullable()->after('gallery')
                    ->comment('JSON array of video embeds');
            });
            echo "✓ Added 'videos' column to news table\n";
        }

        // 2. Add missing 'breaking' column to news table
        if (Schema::hasTable('news') && !Schema::hasColumn('news', 'breaking')) {
            Schema::table('news', function (Blueprint $table) {
                $table->boolean('breaking')->default(false)->after('featured');
            });
            echo "✓ Added 'breaking' column to news table\n";
        }

        // 3. Add missing 'score' column to news table 
        if (Schema::hasTable('news') && !Schema::hasColumn('news', 'score')) {
            Schema::table('news', function (Blueprint $table) {
                $table->integer('score')->default(0)->after('views');
            });
            echo "✓ Added 'score' column to news table\n";
        }

        // 4. Add missing 'featured_at' column to news table
        if (Schema::hasTable('news') && !Schema::hasColumn('news', 'featured_at')) {
            Schema::table('news', function (Blueprint $table) {
                $table->timestamp('featured_at')->nullable()->after('featured');
            });
            echo "✓ Added 'featured_at' column to news table\n";
        }

        // 5. Fix news_comments table - add missing columns carefully
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                // Add status column first
                if (!Schema::hasColumn('news_comments', 'status')) {
                    $table->enum('status', ['active', 'deleted', 'moderated'])->default('active')->after('content');
                }
                
                // Add editing tracking
                if (!Schema::hasColumn('news_comments', 'is_edited')) {
                    $table->boolean('is_edited')->default(false)->after('status');
                }
                if (!Schema::hasColumn('news_comments', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable()->after('is_edited');
                }
                
                // Map existing likes/dislikes to upvotes/downvotes if they don't exist
                if (!Schema::hasColumn('news_comments', 'upvotes') && Schema::hasColumn('news_comments', 'likes')) {
                    $table->integer('upvotes')->default(0)->after('edited_at');
                }
                if (!Schema::hasColumn('news_comments', 'downvotes') && Schema::hasColumn('news_comments', 'dislikes')) {
                    $table->integer('downvotes')->default(0)->after('upvotes');
                }
                if (!Schema::hasColumn('news_comments', 'score')) {
                    $table->integer('score')->default(0)->after('downvotes');
                }
            });
            
            // Copy data from likes/dislikes to upvotes/downvotes if needed
            if (Schema::hasColumn('news_comments', 'likes') && Schema::hasColumn('news_comments', 'upvotes')) {
                DB::statement('UPDATE news_comments SET upvotes = likes WHERE upvotes = 0');
            }
            if (Schema::hasColumn('news_comments', 'dislikes') && Schema::hasColumn('news_comments', 'downvotes')) {
                DB::statement('UPDATE news_comments SET downvotes = dislikes WHERE downvotes = 0');
            }
            if (Schema::hasColumn('news_comments', 'score')) {
                DB::statement('UPDATE news_comments SET score = upvotes - downvotes');
            }
            
            echo "✓ Fixed news_comments table structure\n";
        }

        // 6. Create news_video_embeds table if it doesn't exist
        if (!Schema::hasTable('news_video_embeds')) {
            Schema::create('news_video_embeds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('news_id')->constrained('news')->onDelete('cascade');
                $table->string('platform')->index(); // youtube, twitch-clip, twitch-video, twitter
                $table->string('video_id');
                $table->text('embed_url')->nullable();
                $table->text('original_url');
                $table->string('title')->nullable();
                $table->text('thumbnail')->nullable();
                $table->integer('duration')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['news_id', 'platform']);
                $table->unique(['news_id', 'platform', 'video_id'], 'unique_news_video');
            });
            echo "✓ Created news_video_embeds table\n";
        }

        // 7. Create reports table if it doesn't exist
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->string('reportable_type'); // news_comment, forum_post, etc.
                $table->unsignedBigInteger('reportable_id');
                $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
                $table->text('reason');
                $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
                $table->foreignId('moderator_id')->nullable()->constrained('users')->onDelete('set null');
                $table->text('moderator_notes')->nullable();
                $table->timestamps();

                $table->index(['reportable_type', 'reportable_id']);
                $table->index(['status', 'created_at']);
            });
            echo "✓ Created reports table\n";
        }

        // 8. Create moderation_logs table if it doesn't exist
        if (!Schema::hasTable('moderation_logs')) {
            Schema::create('moderation_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
                $table->string('action');
                $table->string('target_type');
                $table->unsignedBigInteger('target_id');
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['target_type', 'target_id']);
                $table->index(['moderator_id', 'created_at']);
            });
            echo "✓ Created moderation_logs table\n";
        }

        // 9. Add performance indexes
        $this->createPerformanceIndexes();

        echo "✓ Database schema fixes completed successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop created tables
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('news_video_embeds');

        // Remove added columns
        if (Schema::hasTable('news')) {
            Schema::table('news', function (Blueprint $table) {
                $columns = ['featured_at', 'score', 'breaking', 'videos'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('news', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                $columns = ['score', 'downvotes', 'upvotes', 'edited_at', 'is_edited', 'status'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('news_comments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Create performance indexes
     */
    private function createPerformanceIndexes(): void
    {
        try {
            // News table indexes for common queries
            if (Schema::hasTable('news')) {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_news_published_featured 
                              ON news (status, published_at DESC, featured DESC)');
                              
                DB::statement('CREATE INDEX IF NOT EXISTS idx_news_category_published 
                              ON news (category, status, published_at DESC)');
                              
                DB::statement('CREATE INDEX IF NOT EXISTS idx_news_score_views 
                              ON news (score DESC, views DESC)');
                              
                echo "✓ Created news performance indexes\n";
            }

            // News comments indexes
            if (Schema::hasTable('news_comments')) {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_news_comments_status_created 
                              ON news_comments (status, created_at DESC)');
                              
                DB::statement('CREATE INDEX IF NOT EXISTS idx_news_comments_score 
                              ON news_comments (score DESC, created_at DESC)');
                              
                echo "✓ Created news_comments performance indexes\n";
            }

        } catch (\Exception $e) {
            echo "Note: Some indexes may already exist - " . $e->getMessage() . "\n";
        }
    }
};