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
        Schema::table('news', function (Blueprint $table) {
            // Scheduling fields
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
            $table->boolean('auto_publish')->default(false)->after('scheduled_at');
            
            // SEO optimization fields
            $table->boolean('seo_optimized')->default(false)->after('auto_publish');
            $table->integer('read_time')->nullable()->after('seo_optimized');
            
            // Analytics fields
            $table->integer('share_count')->default(0)->after('views');
            $table->timestamp('last_shared_at')->nullable()->after('share_count');
            
            // Indexes for better performance
            $table->index(['status', 'scheduled_at'], 'idx_news_scheduled');
            $table->index(['status', 'published_at', 'featured'], 'idx_news_published_featured');
            $table->index(['category_id', 'status', 'published_at'], 'idx_news_category_published');
            $table->index(['views', 'comments_count', 'score'], 'idx_news_engagement');
        });
        
        // Add full-text search index if using MySQL
        try {
            DB::statement('ALTER TABLE news ADD FULLTEXT(title, excerpt, content)');
        } catch (\Exception $e) {
            // Ignore if not MySQL or already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_news_scheduled');
            $table->dropIndex('idx_news_published_featured');
            $table->dropIndex('idx_news_category_published');
            $table->dropIndex('idx_news_engagement');
            
            // Drop columns
            $table->dropColumn([
                'scheduled_at',
                'auto_publish',
                'seo_optimized',
                'read_time',
                'share_count',
                'last_shared_at'
            ]);
        });
        
        // Drop full-text index if exists
        try {
            DB::statement('ALTER TABLE news DROP INDEX title');
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
    }
};